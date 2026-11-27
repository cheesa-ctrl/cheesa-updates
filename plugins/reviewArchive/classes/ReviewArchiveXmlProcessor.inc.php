<?php

/**
 * @file plugins/generic/reviewArchive/classes/ReviewArchiveXmlProcessor.inc.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewArchiveXmlProcessor
 * @ingroup plugins_generic_reviewArchive
 *
 * @brief Prepares an OJS Native XML file before import:
 *  - labels review-process files with "[REVIEW]" (and the reviewer's name
 *    read from a reviewer certificate) WITHOUT changing their file stage, so
 *    authors still cannot open them (double-blind safe);
 *  - removes embedded files whose content is an exact duplicate of another
 *    file in the same article;
 *  - optionally removes production working documents (GF.doc, copyediting
 *    drafts) and old revisions of non-review files.
 *  Galley files, dependent files and the original submission file are never
 *  removed. No OJS dependencies, so it can be tested from the CLI:
 *    php ReviewArchiveXmlProcessor.inc.php in.xml [out.xml] [--drop-production] [--drop-old-revisions]
 */

require_once(dirname(__FILE__) . '/TextExtractor.inc.php');

class ReviewArchiveXmlProcessor {

	const MARKER = '[REVIEW]';

	/** Native XML file stages that belong to the review process. */
	const REVIEW_STAGES = ['review_file', 'review_attachment', 'review_revision', 'internal_review_file', 'internal_review_revision'];

	/** File names that indicate review-process documents. */
	const REVIEW_NAME_PATTERN = '/review|reviewer|sertifikat|certificate|recognition|penelaah|telaah|(^|[\s_\-,])RV([\s_\-\.]|$)/i';

	/** File names of reviewer certificates. */
	const CERTIFICATE_NAME_PATTERN = '/sertifikat|certificate|recognition/i';

	/** File names of production working documents. */
	const PRODUCTION_NAME_PATTERN = '/copy\s*edit|(^|[\s_\-,])(GF|CE|ED)([\s_\-\.]|$)|inpress|in\s*press|layout|proof/i';

	/** @var DOMDocument */
	protected $_dom;

	/** @var DOMXPath */
	protected $_xpath;

	/** @var TextExtractor */
	protected $_extractor;

	/** @var array */
	protected $_options = [
		'labelReview' => true,
		'dedupe' => true,
		'dropProduction' => false,
		'dropOldRevisions' => false,
	];

	/**
	 * @param $xml string Native XML
	 * @param $options array see $_options
	 * @throws Exception when the XML cannot be parsed
	 */
	public function __construct($xml, $options = []) {
		$this->_options = array_merge($this->_options, array_intersect_key($options, $this->_options));
		$this->_extractor = new TextExtractor();
		$previous = libxml_use_internal_errors(true);
		$this->_dom = new DOMDocument();
		$this->_dom->preserveWhiteSpace = true;
		$ok = $this->_dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (!$ok) throw new Exception('XML tidak valid.');
		$this->_xpath = new DOMXPath($this->_dom);
		$this->_xpath->registerNamespace('n', $this->_dom->documentElement->namespaceURI ?: 'http://pkp.sfu.ca');
	}

	/**
	 * Work out what will be changed, apply it to the loaded document, and
	 * return the plan. Call getXml() afterwards for the result.
	 * @return array
	 */
	public function process() {
		$sizeBefore = strlen($this->_dom->saveXML());
		$articles = [];
		foreach ($this->_xpath->query('//n:article') as $article) {
			$articles[] = $this->_processArticle($article);
		}
		$sizeAfter = strlen($this->_dom->saveXML());
		return [
			'articles' => $articles,
			'sizeBefore' => $sizeBefore,
			'sizeAfter' => $sizeAfter,
			'sizeBeforeMb' => number_format($sizeBefore / 1048576, 2),
			'sizeAfterMb' => number_format($sizeAfter / 1048576, 2),
			'options' => $this->_options,
		];
	}

	/**
	 * @return string
	 */
	public function getXml() {
		return $this->_dom->saveXML();
	}

	//
	// Per article
	//
	/**
	 * @param $article DOMElement
	 * @return array
	 */
	protected function _processArticle($article) {
		$title = $this->_text($this->_xpath->query('.//n:publication/n:title', $article)->item(0));
		$doi = $this->_text($this->_xpath->query('.//n:publication/n:id[@type="doi"]', $article)->item(0));
		$internalId = $this->_text($this->_xpath->query('./n:id[@type="internal"]', $article)->item(0));

		// Files a galley points at must stay untouched.
		$galleyRefs = [];
		foreach ($this->_xpath->query('.//n:submission_file_ref', $article) as $ref) {
			$galleyRefs[$ref->getAttribute('id')] = true;
		}

		// Collect submission files.
		$files = [];
		foreach ($this->_xpath->query('./n:submission_file', $article) as $sf) {
			$id = $sf->getAttribute('id');
			$stage = $sf->getAttribute('stage');
			$name = $this->_fileName($sf);
			$revisions = [];
			foreach ($this->_xpath->query('./n:file', $sf) as $fileEl) {
				$embed = $this->_xpath->query('./n:embed', $fileEl)->item(0);
				$payload = $embed ? preg_replace('/\s+/', '', $embed->textContent) : '';
				$revisions[] = [
					'el' => $fileEl,
					'id' => $fileEl->getAttribute('id'),
					'ext' => strtolower($fileEl->getAttribute('extension')),
					'bytes' => (int) $fileEl->getAttribute('filesize'),
					'xmlBytes' => strlen($payload),
					'hash' => $payload !== '' ? md5($payload) : null,
					'current' => $fileEl->getAttribute('id') === $sf->getAttribute('file_id'),
				];
			}
			$isGalley = isset($galleyRefs[$id]);
			$isReview = !$isGalley && $stage !== 'submission' && $stage !== 'dependent'
				&& (in_array($stage, self::REVIEW_STAGES) || preg_match(self::REVIEW_NAME_PATTERN, $this->_bareName($name)));
			$files[$id] = [
				'el' => $sf,
				'id' => $id,
				'stage' => $stage,
				'name' => $name,
				'isGalley' => $isGalley,
				'isReview' => (bool) $isReview,
				'revisions' => $revisions,
				'actions' => [],
				'removed' => false,
				'newName' => null,
				'reviewer' => null,
			];
		}

		// 1. Labels (and reviewer names from certificates).
		if ($this->_options['labelReview']) {
			foreach ($files as &$f) {
				if (!$f['isReview'] || mb_stripos($f['name'], self::MARKER) !== false) continue;
				$extra = '';
				$bare = $this->_bareName($f['name']);
				if (preg_match(self::CERTIFICATE_NAME_PATTERN, $bare)) {
					$f['reviewer'] = $this->_reviewerFromCertificate($f, $title);
					if ($f['reviewer'] && $f['reviewer']['name']) {
						$extra = 'Reviewer: ' . $f['reviewer']['name'] . ($f['reviewer']['date'] ? ' (' . $f['reviewer']['date'] . ')' : '') . ' - ';
					}
				} elseif (preg_match('/(^|[\s_\-,])RV([\s_\-\.]|$)/', $bare) && !preg_match('/review/i', $bare)) {
					$extra = 'Versi review (RV) - ';
				}
				$f['newName'] = $this->_setLabel($f['el'], self::MARKER . ' ' . $extra);
				$f['actions'][] = 'label';
			}
			unset($f);
		}

		// 2. Exact duplicates.
		if ($this->_options['dedupe']) {
			$this->_dedupe($files);
		}

		// 3. Production working documents.
		if ($this->_options['dropProduction']) {
			foreach ($files as &$f) {
				if ($f['removed'] || $f['isGalley'] || $f['isReview'] || in_array($f['stage'], ['submission', 'dependent'])) continue;
				$ext = $f['revisions'] ? $f['revisions'][0]['ext'] : '';
				$isDoc = in_array($ext, ['doc', 'docx', 'odt', 'rtf']);
				$isProductionStage = in_array($f['stage'], ['copyedit', 'proof', 'production_ready']);
				if ($isDoc && ($isProductionStage || preg_match(self::PRODUCTION_NAME_PATTERN, $this->_bareName($f['name'])))) {
					$this->_removeFile($f, 'production');
				}
			}
			unset($f);
		}

		// 4. Old revisions of non-review files.
		if ($this->_options['dropOldRevisions']) {
			foreach ($files as &$f) {
				if ($f['removed'] || $f['isGalley'] || $f['isReview']) continue;
				foreach ($f['revisions'] as $i => $rev) {
					if (!$rev['current'] && empty($rev['removed'])) $this->_removeRevision($f, $i, 'oldRevision');
				}
			}
			unset($f);
		}

		// Report
		$report = [];
		foreach ($files as $f) {
			$kept = 0; $removedBytes = 0; $removedRevisions = 0;
			foreach ($f['revisions'] as $rev) {
				if (!empty($rev['removed']) || $f['removed']) { $removedBytes += $rev['xmlBytes']; if (!$f['removed']) $removedRevisions++; }
				else $kept++;
			}
			$report[] = [
				'id' => $f['id'],
				'stage' => $f['stage'],
				'name' => $this->_bareName($f['name']),
				'newName' => $f['newName'] !== null ? $this->_bareName($f['newName']) : null,
				'isGalley' => $f['isGalley'],
				'isReview' => $f['isReview'],
				'removed' => $f['removed'],
				'removeReason' => $f['removed'] ? $f['removeReason'] : null,
				'revisions' => count($f['revisions']),
				'revisionsKept' => $kept,
				'revisionsRemoved' => $removedRevisions,
				'revisionReasons' => array_values(array_unique(array_filter(array_map(function($r) { return $r['removeReason'] ?? null; }, $f['revisions'])))),
				'removedBytes' => $removedBytes,
				'reviewer' => $f['reviewer'],
			];
		}
		return [
			'internalId' => $internalId,
			'sourceId' => self::sourceIdFromDoi($doi) ?: $internalId,
			'title' => $title,
			'doi' => $doi,
			'files' => $report,
		];
	}

	/**
	 * Remove files and revisions whose embedded content is identical to
	 * another one in the same article, keeping the most useful copy.
	 * @param $files array (by reference)
	 */
	protected function _dedupe(&$files) {
		$byHash = [];
		foreach ($files as $fid => $f) {
			foreach ($f['revisions'] as $i => $rev) {
				if ($rev['hash'] === null) continue;
				$byHash[$rev['hash']][] = [$fid, $i];
			}
		}
		foreach ($byHash as $occurrences) {
			if (count($occurrences) < 2) continue;
			// Rank: galley > original submission > review (current) > review (old
			// revision) > other current > other old revision.
			usort($occurrences, function($a, $b) use ($files) {
				return $this->_keepRank($files[$b[0]], $files[$b[0]]['revisions'][$b[1]])
					<=> $this->_keepRank($files[$a[0]], $files[$a[0]]['revisions'][$a[1]]);
			});
			array_shift($occurrences); // keeper
			foreach ($occurrences as list($fid, $i)) {
				$f =& $files[$fid];
				if ($f['removed'] || !empty($f['revisions'][$i]['removed'])) { unset($f); continue; }
				if ($f['isGalley'] || in_array($f['stage'], ['submission', 'dependent'])) { unset($f); continue; }
				$rev = $f['revisions'][$i];
				$liveRevisions = count(array_filter($f['revisions'], function($r) { return empty($r['removed']); }));
				if (!$rev['current']) {
					$this->_removeRevision($f, $i, 'duplicate');
				} elseif ($liveRevisions === 1) {
					$this->_removeFile($f, 'duplicate');
				}
				unset($f);
			}
		}
	}

	/**
	 * @param $f array
	 * @param $rev array
	 * @return int
	 */
	protected function _keepRank($f, $rev) {
		if ($f['isGalley']) return 100;
		if ($f['stage'] === 'submission' && $rev['current']) return 90;
		if ($f['isReview']) return $rev['current'] ? 80 : 60;
		if ($rev['current']) return $f['stage'] === 'attachment' ? 50 : 55;
		return 10;
	}

	/**
	 * @param $f array (by reference)
	 * @param $reason string
	 */
	protected function _removeFile(&$f, $reason) {
		$f['el']->parentNode->removeChild($f['el']);
		$f['removed'] = true;
		$f['removeReason'] = $reason;
		$f['actions'][] = 'remove';
	}

	/**
	 * @param $f array (by reference)
	 * @param $i int revision index
	 * @param $reason string
	 */
	protected function _removeRevision(&$f, $i, $reason) {
		$el = $f['revisions'][$i]['el'];
		$el->parentNode->removeChild($el);
		$f['revisions'][$i]['removed'] = true;
		$f['revisions'][$i]['removeReason'] = $reason;
		$f['actions'][] = 'pruneRevision';
	}

	//
	// Reviewer certificates
	//
	/**
	 * Reviewer name and date from a certificate (CHEESA template: the
	 * reviewer's name is the first person-like line; the date reads
	 * "26 Oktober 2020").
	 * @param $f array
	 * @param $title string article title, to skip its words
	 * @return array|null ['name' => string|null, 'date' => string|null]
	 */
	protected function _reviewerFromCertificate($f, $title) {
		$current = null;
		foreach ($f['revisions'] as $rev) if ($rev['current']) $current = $rev;
		if (!$current) $current = $f['revisions'][0] ?? null;
		if (!$current) return null;
		$embed = $this->_xpath->query('./n:embed', $current['el'])->item(0);
		if (!$embed) return null;
		$data = base64_decode(preg_replace('/\s+/', '', $embed->textContent));
		if ($data === false) return null;
		list(, $text) = $this->_extractor->extract($data, $current['ext']);
		if (!$text) return null;
		return self::parseCertificate($text, $title);
	}

	/**
	 * @param $text string
	 * @param $title string
	 * @return array|null
	 */
	public static function parseCertificate($text, $title = '') {
		$text = (string) $text;
		// Teks hasil ekstraksi PDF kerap memuat byte UTF-8 tidak valid; tanpa dibersihkan,
		// semua regex dengan flag /u gagal diam-diam. Bersihkan dulu.
		if (function_exists('mb_scrub')) {
			$text = mb_scrub($text, 'UTF-8');
		} elseif (!mb_check_encoding($text, 'UTF-8')) {
			$clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
			if ($clean !== false) $text = $clean;
		}
		$text = str_replace(["\r\n", "\r"], "\n", $text);
		if (!preg_match('/penghargaan|berkontribusi|melakukan\s*review|recognition|reviewer|appreciation|menugaskan|surat\s*tugas/i', preg_replace('/\s+/', ' ', $text))) {
			return null;
		}

		$months = [
			'januari' => 'Jan', 'january' => 'Jan', 'februari' => 'Feb', 'february' => 'Feb', 'maret' => 'Mar', 'march' => 'Mar',
			'april' => 'Apr', 'mei' => 'Mei', 'may' => 'Mei', 'juni' => 'Jun', 'june' => 'Jun', 'juli' => 'Jul', 'july' => 'Jul',
			'agustus' => 'Agu', 'august' => 'Agu', 'september' => 'Sep', 'oktober' => 'Okt', 'october' => 'Okt',
			'november' => 'Nov', 'desember' => 'Des', 'december' => 'Des',
		];
		$date = null;
		$flat = preg_replace('/\n/', '', $text); // PDF fragments: "2","6"," ","Novem","ber"
		if (preg_match('/(\d{1,2})\s*(' . implode('|', array_keys($months)) . ')\s*(\d{4})/i', $flat, $m)) {
			$date = ((int) $m[1]) . ' ' . $months[strtolower($m[2])] . ' ' . $m[3];
		}

		$flatSp = trim(preg_replace('/\s+/u', ' ', $text));
		// Reviewer adalah PENERIMA tugas, bukan penanda tangan. Cari nama setelah
		// "Menugaskan kepada" / "Diberikan kepada" / "Presented to", dsb.
		$assign = 'menugaskan\s*kepada|ditugaskan\s*kepada|diberikan\s*kepada|dianugerahkan\s*kepada|presented\s*to|awarded\s*to|given\s*to';
		$fieldStop = 'instansi|nidn|nip|nik|jabatan|sebagai|alamat|asal|prodi|program\s*studi|fakultas|email|tanggal|nomor|no\.';
		$name = null;
		if (preg_match('/\b(?:' . $assign . ')\b.{0,300}?\bnama\b\s*:?\s*(.+?)\s*(?:\b(?:' . $fieldStop . ')\b|$)/isu', $flatSp, $mm)) {
			$name = self::_cleanName($mm[1]);
		}
		if (!$name && preg_match('/\b(?:' . $assign . ')\b\s*:?\s*([\p{L}][\p{L}\'’\.\- ]{2,70}?)\s*(?:\b(?:' . $fieldStop . '|menelaah|me-?review|artikel|paper)\b|$)/isu', $flatSp, $mm)) {
			$name = self::_cleanName($mm[1]);
		}
		if ($name) return ['name' => $name, 'date' => $date];

		// Fallback: pindai baris, tapi LEWATI blok penanda tangan (teks sebelum "kepada").
		$body = $text;
		if (preg_match('/\b(?:' . $assign . ')\b/isu', $text, $om, PREG_OFFSET_CAPTURE)) {
			$body = substr($text, $om[0][1]);
		}
		$titleWords = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title)));
		$stop = '/\b(issn|website|publisher|sebagai|penghargaan|print|online|sertifikat|certificate|recognition|editor|chief|ketua|journal|jurnal|cheesa|chemical|engineering|universitas|university|institut|pgri|madiun|diberikan|kepada|menugaskan|awarded|presented|reviewer|instansi|nidn|nip|jabatan|artikel|article|berjudul|http|www|' . implode('|', array_keys($months)) . ')\b/i';
		$namePattern = '/^(?:(?:Prof|Dr|Ir|Drs|Dra|drh|dr|H|Hj|Apt|Assoc)\.?\s+)*[A-Z][\p{L}\'’\-]+(?:\s+(?:[A-Z][\p{L}\'’\-\.]*|bin|binti|van|de|al|el)){1,5}(?:\s*,\s*[\p{L}\.\s]{1,20}){0,4}$/u';
		foreach (preg_split('/\n+/', $body) as $line) {
			$line = trim(preg_replace('/\s+/u', ' ', $line), " \t\"“”'");
			$line = preg_replace('/^nama\s*:?\s*/i', '', $line);
			if (mb_strlen($line) < 5 || mb_strlen($line) > 90) continue;
			if (preg_match($stop, $line)) continue;
			if (!preg_match($namePattern, $line)) continue;
			if ($line === mb_strtoupper($line)) continue;
			$words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($line)));
			if ($titleWords && count(array_diff($words, $titleWords)) === 0) continue;
			$n = self::_cleanName($line);
			if ($n) return ['name' => $n, 'date' => $date];
		}
		return ['name' => null, 'date' => $date];
	}

	/** Bersihkan & validasi kandidat nama; tolak yang jelas bukan nama reviewer. */
	protected static function _cleanName($name) {
		if ($name === null) return null;
		$name = trim(preg_replace('/\s+/u', ' ', $name), " \t\"“”'.,:;-");
		if (mb_strlen($name) < 3 || mb_strlen($name) > 90) return null;
		if (preg_match('/\d|editor|chief|ketua|instansi|nidn|nip|jabatan|reviewer|sebagai|surat|tugas|universitas|institut|fakultas/iu', $name)) return null;
		if (!preg_match('/\p{L}\s+\p{L}/u', $name)) return null; // minimal dua kata
		return $name;
	}

	/**
	 * "10.25273/cheesa.v3i2.7688.88-98" -> "7688"
	 * @param $doi string
	 * @return string|null
	 */
	public static function sourceIdFromDoi($doi) {
		if (preg_match('/\.v\d+i\d+\.(\d+)/i', (string) $doi, $m)) return $m[1];
		return null;
	}

	//
	// Helpers
	//
	/**
	 * @param $sf DOMElement
	 * @return string first <name>
	 */
	protected function _fileName($sf) {
		return $this->_text($this->_xpath->query('./n:name', $sf)->item(0));
	}

	/**
	 * "cheesa, Journal manager, review 1.doc" -> "review 1.doc"
	 * @param $name string
	 * @return string
	 */
	protected function _bareName($name) {
		return preg_replace('/^[^,]{1,60},\s*[^,]{1,60},\s*/', '', $name, 1);
	}

	/**
	 * Insert a label in front of the original file name in every <name>,
	 * after OJS's "uploader, role, " prefix when there is one.
	 * @param $sf DOMElement
	 * @param $label string
	 * @return string the first new name
	 */
	protected function _setLabel($sf, $label) {
		$first = null;
		foreach ($this->_xpath->query('./n:name', $sf) as $nameEl) {
			$name = $nameEl->textContent;
			if (preg_match('/^([^,]{1,60},\s*[^,]{1,60},\s*)(.*)$/s', $name, $m)) {
				$new = $m[1] . $label . $m[2];
			} else {
				$new = $label . $name;
			}
			while ($nameEl->firstChild) $nameEl->removeChild($nameEl->firstChild);
			$nameEl->appendChild($this->_dom->createTextNode($new));
			if ($first === null) $first = $new;
		}
		return (string) $first;
	}

	/**
	 * @param $node DOMNode|null
	 * @return string
	 */
	protected function _text($node) {
		return $node ? trim($node->textContent) : '';
	}
}

// CLI: php ReviewArchiveXmlProcessor.inc.php in.xml [out.xml] [--drop-production] [--drop-old-revisions] [--no-dedupe] [--no-label]
if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
	$args = array_slice($argv, 1);
	$flags = array_filter($args, function($a) { return strpos($a, '--') === 0; });
	$paths = array_values(array_diff($args, $flags));
	$processor = new ReviewArchiveXmlProcessor(file_get_contents($paths[0]), [
		'dropProduction' => in_array('--drop-production', $flags),
		'dropOldRevisions' => in_array('--drop-old-revisions', $flags),
		'dedupe' => !in_array('--no-dedupe', $flags),
		'labelReview' => !in_array('--no-label', $flags),
	]);
	$plan = $processor->process();
	printf("ukuran: %.2f MB -> %.2f MB\n", $plan['sizeBefore'] / 1048576, $plan['sizeAfter'] / 1048576);
	foreach ($plan['articles'] as $a) {
		printf("Artikel %s (sumber %s) DOI %s\n  %s\n", $a['internalId'], $a['sourceId'], $a['doi'], mb_substr($a['title'], 0, 90));
		foreach ($a['files'] as $f) {
			$status = $f['removed'] ? 'HAPUS (' . $f['removeReason'] . ')' : ($f['isGalley'] ? 'galley' : ($f['newName'] ? 'LABEL' : 'tetap'));
			if (!$f['removed'] && $f['revisionsRemoved']) $status .= sprintf(', -%d revisi (%s)', $f['revisionsRemoved'], implode('/', $f['revisionReasons']));
			printf("  #%-6s %-11s %-28s %s\n", $f['id'], $f['stage'], $status, $f['newName'] ?: $f['name']);
		}
	}
	if (!empty($paths[1])) file_put_contents($paths[1], $processor->getXml());
}
