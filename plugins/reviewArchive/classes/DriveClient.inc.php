<?php

/**
 * @file plugins/generic/reviewArchive/classes/DriveClient.inc.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class DriveClient
 * @ingroup plugins_generic_reviewArchive
 *
 * @brief Read a public ("Anyone with the link") Google Drive folder with an
 *        API key, and match one article to its sub-folder inside a shared
 *        "collection" folder.
 *
 * The matching key is deliberately layered, because submission IDs change
 * when a journal is migrated to a new OJS but the DOI does not. A collection
 * sub-folder named like "Art-1-24394-Mara" is matched to an article by, in
 * order of confidence: an explicit per-article override, the number inside
 * the folder name (new submission ID or DOI number), then the first author's
 * family name. Everything fails open: any network or key problem just means
 * the Drive section is skipped, never a broken tab.
 */

class DriveClient {

	const GAPP_FOLDER = 'application/vnd.google-apps.folder';
	const MAX_SUBFOLDERS = 400;
	const MAX_NODES = 1500;
	const MAX_DEPTH = 8;

	/**
	 * Pull a folder ID out of any Drive link shape, or a raw ID.
	 * @param $link string
	 * @return string|null
	 */
	public static function folderId($link) {
		$link = trim((string) $link);
		if ($link === '') return null;
		if (preg_match('#/folders/([A-Za-z0-9_-]{8,})#', $link, $m)) return $m[1];
		if (preg_match('#[?&]id=([A-Za-z0-9_-]{8,})#', $link, $m)) return $m[1];
		if (preg_match('#/d/([A-Za-z0-9_-]{8,})#', $link, $m)) return $m[1];
		if (preg_match('#^[A-Za-z0-9_-]{8,}$#', $link)) return $link;
		return null;
	}

	/**
	 * Simple GET (curl, falling back to streams).
	 * @param $url string
	 * @return array [int httpCode, string body]
	 * @throws Exception
	 */
	public static function httpGet($url) {
		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 12,
				CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_USERAGENT => 'CHEESA-ReviewArchive',
			]);
			$body = curl_exec($ch);
			$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$err = curl_error($ch);
			curl_close($ch);
			if ($body === false) throw new Exception('koneksi gagal: ' . $err);
			return [$code, $body];
		}
		$ctx = stream_context_create(['http' => ['timeout' => 25], 'ssl' => ['verify_peer' => true]]);
		$body = @file_get_contents($url, false, $ctx);
		if ($body === false) throw new Exception('tidak bisa menghubungi Google.');
		return [200, $body];
	}

	/**
	 * Immediate children of a folder (with paging).
	 * @param $fid string
	 * @param $key string
	 * @return array of [id,name,mimeType,size,modifiedTime]
	 * @throws Exception
	 */
	public static function children($fid, $key) {
		$all = [];
		$pageToken = '';
		$guard = 0;
		do {
			$q = rawurlencode("'" . $fid . "' in parents and trashed = false");
			$url = 'https://www.googleapis.com/drive/v3/files?q=' . $q . '&key=' . rawurlencode($key)
				. '&fields=' . rawurlencode('nextPageToken,files(id,name,mimeType,size,modifiedTime)')
				. '&pageSize=1000&orderBy=' . rawurlencode('folder,name_natural')
				. '&supportsAllDrives=true&includeItemsFromAllDrives=true';
			if ($pageToken) $url .= '&pageToken=' . rawurlencode($pageToken);
			list($code, $body) = self::httpGet($url);
			$j = json_decode($body, true);
			if ($code >= 400 || isset($j['error'])) throw new Exception($j['error']['message'] ?? ('HTTP ' . $code));
			foreach (($j['files'] ?? []) as $f) $all[] = $f;
			$pageToken = $j['nextPageToken'] ?? '';
		} while ($pageToken && count($all) < 5000 && ++$guard < 10);
		return $all;
	}

	/**
	 * The immediate sub-folders of the collection folder, as [id => , name => ].
	 * @param $collectionId string
	 * @param $key string
	 * @return array
	 * @throws Exception
	 */
	public static function subfolders($collectionId, $key) {
		$out = [];
		$i = 0;
		foreach (self::children($collectionId, $key) as $c) {
			if (($c['mimeType'] ?? '') !== self::GAPP_FOLDER) continue;
			$out[] = ['id' => $c['id'], 'name' => $c['name']];
			if (++$i >= self::MAX_SUBFOLDERS) break;
		}
		return $out;
	}

	/**
	 * Group one article folder by its structure, descending through ALL nested
	 * sub-folders (e.g. "2. Review File / 1. Reviewer"). Every folder that
	 * directly holds files becomes one group, labelled by its path relative to
	 * the article folder. Loose files in the article folder itself go to a
	 * "(Folder utama)" group. Depth, folder and node caps guard runaway trees.
	 * @param $fid string
	 * @param $key string
	 * @return array of ['name' => , 'files' => [[id,name,mimeType,size], ...]]
	 * @throws Exception
	 */
	public static function tree($fid, $key) {
		$groups = [];
		$ctr = ['nodes' => 0, 'folders' => 0];
		self::_walk($fid, $key, '', 0, $groups, $ctr);
		return $groups;
	}

	/**
	 * Depth-first walk used by tree().
	 * @param $fid string current folder
	 * @param $key string
	 * @param $path string path label so far ('' at the article root)
	 * @param $depth int
	 * @param $groups array by reference, collected groups
	 * @param $ctr array by reference, ['nodes','folders'] running caps
	 * @throws Exception
	 */
	protected static function _walk($fid, $key, $path, $depth, &$groups, &$ctr) {
		if ($depth > self::MAX_DEPTH || $ctr['folders'] > self::MAX_SUBFOLDERS || $ctr['nodes'] > self::MAX_NODES) return;
		$files = [];
		$subs = [];
		foreach (self::children($fid, $key) as $c) {
			if (($c['mimeType'] ?? '') === self::GAPP_FOLDER) $subs[] = $c;
			else $files[] = $c;
		}
		if ($files) {
			$groups[] = ['name' => ($path === '' ? '(Folder utama)' : $path), 'files' => $files];
			$ctr['nodes'] += count($files);
		}
		foreach ($subs as $sf) {
			if (++$ctr['folders'] > self::MAX_SUBFOLDERS || $ctr['nodes'] > self::MAX_NODES) break;
			$childPath = ($path === '' ? (string) $sf['name'] : $path . ' / ' . $sf['name']);
			self::_walk($sf['id'], $key, $childPath, $depth + 1, $groups, $ctr);
		}
	}

	/**
	 * Pick the collection sub-folder that belongs to one article.
	 *
	 * @param $subfolders array from subfolders(): [['id','name'], ...]
	 * @param $keys array match signals:
	 *        'ids'      => numeric strings (submission ID, DOI number) — strong
	 *        'surnames' => lower-case author family names — weaker
	 * @return array|null the winning sub-folder plus 'score' and 'reason', or null
	 */
	public static function matchFolder($subfolders, $keys) {
		$ids = array_values(array_unique(array_filter((array) ($keys['ids'] ?? []), 'strlen')));
		$surnames = array_values(array_filter(array_map(function ($s) {
			return self::_normName($s);
		}, (array) ($keys['surnames'] ?? [])), 'strlen'));

		$best = null;
		foreach ($subfolders as $sf) {
			$name = (string) $sf['name'];
			$numbers = self::_numbers($name);
			$score = 0;
			$reason = '';

			// Strongest: a whole-number token in the folder equals an article ID.
			foreach ($ids as $id) {
				if (in_array($id, $numbers, true)) { $score = 100; $reason = 'ID/DOI ' . $id; break; }
			}
			// Author family name appears as a word in the folder name.
			if ($score === 0 && $surnames) {
				$folderNorm = self::_normName($name);
				foreach ($surnames as $sn) {
					if ($sn === '' || mb_strlen($sn) < 3) continue;
					if (preg_match('/(^|[^a-z])' . preg_quote($sn, '/') . '([^a-z]|$)/u', $folderNorm)) {
						$score = 70; $reason = 'nama ' . $sn; break;
					}
				}
			}

			if ($score > 0 && (!$best || $score > $best['score'])) {
				$best = ['id' => $sf['id'], 'name' => $name, 'score' => $score, 'reason' => $reason];
				if ($score >= 100) break;
			}
		}
		return $best;
	}

	/**
	 * Human file size.
	 * @param $bytes int
	 * @return string
	 */
	public static function humanSize($bytes) {
		$bytes = (int) $bytes;
		if ($bytes <= 0) return '';
		$u = ['B', 'KB', 'MB', 'GB'];
		$i = (int) floor(log($bytes, 1024));
		$i = max(0, min($i, 3));
		return round($bytes / pow(1024, $i), ($i >= 2 ? 1 : 0)) . ' ' . $u[$i];
	}

	/**
	 * A direct Drive link for a folder (used so the editor can open the match).
	 * @param $fid string
	 * @return string
	 */
	public static function folderUrl($fid) {
		return 'https://drive.google.com/drive/folders/' . rawurlencode($fid);
	}

	/**
	 * Does this name look like a reviewer certificate (CHEESA "Surat Tugas")?
	 * Kept deliberately narrow: a cover letter, a reviewer-comment file or a
	 * "review-assignment" manuscript must NOT be flagged.
	 * @param $name string
	 * @return bool
	 */
	public static function looksLikeCertificate($name) {
		return (bool) preg_match('/surat\s*tugas|sertifik(?:at|asi)?|\bcertificate\b|surat\s*penugasan/iu', (string) $name);
	}

	/**
	 * Does this name look like an activity / history log?
	 * @param $name string
	 * @return bool
	 */
	public static function looksLikeActivityLog($name) {
		return (bool) preg_match('/activity\s*log|activity|history|riwayat|aktivitas|\blog\b/iu', (string) $name);
	}

	/**
	 * Numeric tokens of length >= 3 in a string (e.g. "Art-1-24394-Mara" -> ["24394"]).
	 * One- and two-digit tokens (the "Art-1" sequence) are ignored on purpose.
	 * @param $s string
	 * @return array of numeric strings
	 */
	protected static function _numbers($s) {
		preg_match_all('/\d{3,}/', (string) $s, $m);
		return $m[0];
	}

	/**
	 * Lower-case, strip accents and non-letters-to-spaces, for name matching.
	 * @param $s string
	 * @return string
	 */
	protected static function _normName($s) {
		$s = (string) $s;
		if (function_exists('iconv')) {
			$t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
			if ($t !== false) $s = $t;
		}
		$s = mb_strtolower($s, 'UTF-8');
		$s = preg_replace('/[^a-z]+/', ' ', $s);
		return trim(preg_replace('/\s+/', ' ', $s));
	}
}
