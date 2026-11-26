<?php

/**
 * @file plugins/generic/reviewArchive/classes/JudolScanner.inc.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class JudolScanner
 * @ingroup plugins_generic_reviewArchive
 *
 * @brief Looks for online-gambling ("judol") spam in an OJS Native XML file:
 *  in the metadata (titles, abstracts, references, URLs ...) and in the
 *  embedded files (.doc, .docx, .pdf, .html, .txt ...).
 *
 *  The pattern list is deliberately conservative: words that also appear in
 *  science papers (deposit, olympus, roulette, habanero, jackpot, bandar ...)
 *  are only matched in gambling combinations, so ordinary articles are not
 *  blocked. Adjust PATTERNS below if needed.
 *
 *  This class has no OJS dependencies, so it can also be run from the CLI:
 *    php JudolScanner.inc.php file.xml
 */

require_once(dirname(__FILE__) . '/TextExtractor.inc.php');

class JudolScanner extends TextExtractor {

	/** Gambling patterns (PCRE, matched case-insensitively on normalised text). */
	const PATTERNS = [
		'gacor',
		'slot\s*(online|gacor|88|777|resmi|terpercaya|deposit|demo|pulsa|dana|maxwin|thailand|kamboja)',
		'slot88|slot777|joker123|sbobet|maxbet|idn\s*(poker|slot|play|live)',
		'situs\s*(judi|slot|togel|toto|casino|poker|gacor|bola)',
		'agen\s*(judi|slot|togel|toto|casino|poker|bola|sbobet)',
		'bandar\s*(judi|slot|togel|toto|casino|poker|bola|darat)',
		'judi\s*(online|slot|bola|togel|casino|poker|kartu)',
		'togel',
		'toto\s*(macau|sgp|hk|sdy|online|4d|togel|slot)',
		'max\s*win|maxwin',
		'(casino|kasino)\s*online|live\s*casino|poker\s*online|domino\s*qq|bandarqq|pkv\s*games',
		'pragmatic\s*play|pg\s*soft|habanero\s*slot|spadegaming|microgaming',
		'gates\s*of\s*olympus|olympus\s*1000|starlight\s*princess|sweet\s*bonanza|mahjong\s*ways|wild\s*west\s*gold|aztec\s*gems',
		'scatter\s*(hitam|merah|emas|mahjong)',
		'rtp\s*(live|slot|gacor|tertinggi|hari\s*ini)',
		'bocoran\s*(slot|rtp|togel|admin)|pola\s*(gacor|slot|maxwin)|jam\s*gacor|anti\s*rungkad|jp\s*paus|auto\s*jp|sensasional\s*jp',
		'link\s*alternatif|daftar\s*(slot|judi|togel)|login\s*(slot|judi|togel)',
		'bonus\s*new\s*member|bonus\s*(deposit|cashback|rollingan)|freebet|free\s*spin',
		'deposit\s*(pulsa|dana|ovo|gopay|qris|tanpa\s*potongan|5\s*rb|10\s*rb|via\s*(pulsa|dana|qris))',
		'depo\s*\d+\s*(k|rb|ribu)|wd\s*(lancar|cepat|kilat)|withdraw\s*(lancar|cepat)',
		'sabung\s*ayam\s*online|tembak\s*ikan\s*online',
	];

	/** Code that should never appear in article metadata. */
	const CODE_PATTERNS = [
		'<\s*script\b',
		'<\s*iframe\b',
		'display\s*:\s*none',
		'visibility\s*:\s*hidden',
		'position\s*:\s*absolute\s*;?\s*(left|top)\s*:\s*-\d{3,}',
		'font-size\s*:\s*0(px)?\s*[;"\']',
	];

	/** Max findings kept per location, and in total. */
	const MAX_PER_LOCATION = 5;
	const MAX_TOTAL = 100;

	/**
	 * Jaring pengaman agar impor besar / PDF bermasalah tak melewati timeout web-server.
	 * Untuk berkas per-artikel normal, cap ini praktis tak tersentuh (tiap berkas dipindai
	 * penuh). Berkas tunggal > cap dipotong dan dilaporkan. Cap dibuat konservatif agar satu
	 * PDF yang memicu regex "macet" tidak pernah membuat permintaan menggantung.
	 */
	const MAX_FILE_BYTES = 4194304;   // 4 MB per-embed (PDF galley/review normal jauh di bawah ini)
	const MAX_TEXT = 1048576;         // 1 MB teks untuk pemindai regex
	const TIME_BUDGET = 20;           // detik: jaring pengaman; berkas sisa dilewati & dilaporkan

	/** @var array */
	protected $_findings = [];

	/** @var array Locations that could not be scanned (e.g. .rar). */
	protected $_skipped = [];

	/** @var int */
	protected $_scannedFiles = 0;

	/** @var float Waktu mulai pindai (untuk batas waktu). */
	protected $_start = 0.0;

	/**
	 * Scan a Native XML file.
	 * @param $path string
	 * @return array ['findings' => [...], 'skipped' => [...], 'scannedFiles' => int, 'error' => string|null]
	 */
	public function scanFile($path) {
		$xml = @file_get_contents($path);
		if ($xml === false) {
			return $this->_result('Berkas tidak dapat dibaca.');
		}
		return $this->scanXmlString($xml);
	}

	/**
	 * Scan Native XML content.
	 * @param $xml string
	 * @return array See scanFile().
	 */
	public function scanXmlString($xml) {
		$this->_findings = [];
		$this->_skipped = [];
		$this->_scannedFiles = 0;
		$this->_start = microtime(true);

		// Batasi langkah regex agar pola "catastrophic backtracking" pada PDF tertentu
		// gagal cepat alih-alih menggantung sampai timeout web-server.
		@ini_set('pcre.backtrack_limit', '1000000');
		@ini_set('pcre.recursion_limit', '100000');

		$previous = libxml_use_internal_errors(true);
		$dom = new DOMDocument();
		$loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE | LIBXML_COMPACT);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if (!$loaded) {
			// Not well-formed: still scan the raw text so nothing slips through.
			$this->_scanText($this->_stripBase64($xml), 'XML (tidak valid, dipindai sebagai teks)', true);
			return $this->_result('XML tidak valid; dipindai sebagai teks biasa.');
		}

		$this->_walk($dom->documentElement);
		return $this->_result(null);
	}

	//
	// Walking the XML
	//
	/**
	 * @param $node DOMNode
	 */
	protected function _walk($node) {
		foreach ($node->childNodes as $child) {
			if (count($this->_findings) >= self::MAX_TOTAL) return;
			if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
				$text = trim($child->nodeValue);
				if ($text !== '') {
					$this->_scanText($text, $this->_describeMetadata($node), true);
				}
				continue;
			}
			if ($child->nodeType !== XML_ELEMENT_NODE) continue;

			// Attributes such as href / url.
			foreach ($child->attributes as $attribute) {
				if (strlen($attribute->value) > 3) {
					$this->_scanText($attribute->value, $this->_describeMetadata($child) . ' @' . $attribute->name, true);
				}
			}

			if ($child->localName === 'embed') {
				$this->_scanEmbed($child);
				continue;
			}
			$this->_walk($child);
		}
	}

	/**
	 * Decode an <embed encoding="base64"> and scan the file inside.
	 * @param $embed DOMElement
	 */
	protected function _scanEmbed($embed) {
		$fileElement = $embed->parentNode;
		$extension = strtolower((string) ($fileElement instanceof DOMElement ? $fileElement->getAttribute('extension') : ''));
		$location = $this->_describeFile($embed);

		// Batas waktu: setelah TIME_BUDGET, lewati berkas sisa agar tidak kena timeout server.
		if ((microtime(true) - $this->_start) > self::TIME_BUDGET) {
			$this->_skipped[] = $location . ' (dilewati: batas waktu pindai tercapai)';
			return;
		}

		$encoding = strtolower($embed->getAttribute('encoding'));
		$data = trim($embed->textContent);
		if ($encoding === 'base64' || $encoding === '') {
			$data = base64_decode($data, false);
			if ($data === false) {
				$this->_skipped[] = $location . ' (base64 rusak)';
				return;
			}
		}
		$this->_scannedFiles++;
		// Batasi besar berkas yang diekstraksi agar pemindaian cepat. Dilaporkan bila terpotong.
		if (strlen($data) > self::MAX_FILE_BYTES) {
			$data = substr($data, 0, self::MAX_FILE_BYTES);
			$this->_skipped[] = $location . ' (berkas besar: hanya ' . round(self::MAX_FILE_BYTES / 1048576) . ' MB pertama dipindai)';
		}

		$type = $this->_detectType($data, $extension);
		switch ($type) {
			case 'pdf':
				$this->_scanText($this->_pdfText($data), $location, false);
				break;
			case 'zip':
				$text = $this->_zipText($data);
				if ($text === null) {
					$this->_skipped[] = $location . ' (arsip ZIP tidak dapat dibuka di server)';
					$text = $this->_binaryText($data);
				}
				$this->_scanText($text, $location, false);
				break;
			case 'rar':
			case '7z':
				$this->_skipped[] = $location . ' (arsip ' . strtoupper($type) . ', isinya tidak dapat dipindai)';
				break;
			case 'image':
			case 'media':
				// Nothing readable inside.
				break;
			case 'text':
				$this->_scanText($data, $location, true);
				break;
			default: // .doc, .rtf, unknown binary
				$this->_scanText($this->_binaryText($data), $location, false);
		}
	}

	//
	// Matching
	//
	/**
	 * @param $text string
	 * @param $location string
	 * @param $isMetadata bool Metadata is also checked for hidden HTML/JS.
	 */
	protected function _scanText($text, $location, $isMetadata) {
		if ($text === '' || $text === null) return;
		// Batasi panjang teks untuk pemindai regex: cukup untuk menangkap spam, cepat.
		if (strlen($text) > self::MAX_TEXT) {
			$text = substr($text, 0, self::MAX_TEXT);
		}
		if (!mb_check_encoding($text, 'UTF-8')) {
			$text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
		}
		$normal = preg_replace('/\s+/u', ' ', mb_strtolower($text, 'UTF-8'));
		$deobfuscated = $this->_deobfuscate($normal);

		$count = 0;
		foreach (self::PATTERNS as $pattern) {
			$regex = '/(?<![a-z])(' . $pattern . ')(?![a-z])/iu';
			$found = false;
			if (preg_match_all($regex, $normal, $m, PREG_OFFSET_CAPTURE)) {
				foreach ($m[1] as $hit) {
					$this->_add($location, $hit[0], $this->_snippet($normal, $hit[1], strlen($hit[0])), 'judol');
					$found = true;
					if (++$count >= self::MAX_PER_LOCATION) return;
				}
			}
			if (!$found && $deobfuscated !== $normal && preg_match_all($regex, $deobfuscated, $m, PREG_OFFSET_CAPTURE)) {
				foreach ($m[1] as $hit) {
					$this->_add($location, $hit[0], '(teks disamarkan) ' . $this->_snippet($deobfuscated, $hit[1], strlen($hit[0])), 'judol');
					if (++$count >= self::MAX_PER_LOCATION) return;
				}
			}
		}
		if ($isMetadata) {
			foreach (self::CODE_PATTERNS as $pattern) {
				if (preg_match('/' . $pattern . '/iu', $normal, $m, PREG_OFFSET_CAPTURE)) {
					$this->_add($location, $m[0][0], $this->_snippet($normal, $m[0][1], strlen($m[0][0])), 'kode');
					if (++$count >= self::MAX_PER_LOCATION) return;
				}
			}
		}
	}

	/**
	 * Undo common spam obfuscation without gluing ordinary words together:
	 *  - letters spelled out one by one: "s l o t", "g.a.c.o.r", "t-o-g-e-l"
	 *  - leetspeak inside a single word: "sl0t", "g4c0r", "t0g3l"
	 * @param $normal string lower-cased text
	 * @return string
	 */
	protected function _deobfuscate($normal) {
		$s = preg_replace_callback(
			'/(?<![\p{L}\p{N}])(?:[\p{L}\p{N}][ \.\-_\*\|]){3,}[\p{L}\p{N}](?![\p{L}\p{N}])/u',
			function($m) { return preg_replace('/[ \.\-_\*\|]/u', '', $m[0]); },
			$normal
		);
		$s = preg_replace_callback(
			'/(?<![\p{L}\p{N}])(?=[\p{L}\p{N}@$]*\p{L})(?=[\p{L}\p{N}@$]*[0-9@$])[\p{L}\p{N}@$]{4,}/u',
			function($m) {
				// Leave pure codes alone (e.g. "h2o", "co2", "slot88" is handled by PATTERNS).
				return strtr($m[0], ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);
			},
			$s
		);
		return $s;
	}

	/**
	 * @param $text string
	 * @param $offset int byte offset
	 * @param $length int
	 * @return string
	 */
	protected function _snippet($text, $offset, $length) {
		$start = max(0, $offset - 60);
		$snippet = substr($text, $start, $length + 120);
		$snippet = mb_convert_encoding($snippet, 'UTF-8', 'UTF-8'); // drop cut multibyte chars
		return ($start > 0 ? '…' : '') . trim($snippet) . '…';
	}

	/**
	 * @param $location string
	 * @param $term string
	 * @param $snippet string
	 * @param $kind string judol|kode
	 */
	protected function _add($location, $term, $snippet, $kind) {
		if (count($this->_findings) >= self::MAX_TOTAL) return;
		foreach ($this->_findings as $f) {
			if ($f['location'] === $location && $f['term'] === $term) return;
		}
		$this->_findings[] = [
			'location' => $location,
			'term' => $term,
			'snippet' => $snippet,
			'kind' => $kind,
		];
	}

	//
	// Describing where something was found
	//
	/**
	 * e.g. "Artikel 7742 › publication › abstract (en_US)"
	 * @param $node DOMNode
	 * @return string
	 */
	protected function _describeMetadata($node) {
		$parts = [];
		for ($n = $node; $n && $n instanceof DOMElement; $n = $n->parentNode) {
			$label = $n->localName;
			if ($n->hasAttribute('locale') && $n !== $node->ownerDocument->documentElement) {
				$label .= ' (' . $n->getAttribute('locale') . ')';
			}
			array_unshift($parts, $label);
			if (in_array($n->localName, ['article', 'issue'])) break;
		}
		return $this->_articleLabel($node) . ' › ' . implode(' › ', array_slice($parts, 1));
	}

	/**
	 * e.g. "Artikel 7742 › berkas #22364 "Art-4.pdf""
	 * @param $embed DOMElement
	 * @return string
	 */
	protected function _describeFile($embed) {
		$label = 'berkas';
		for ($n = $embed->parentNode; $n && $n instanceof DOMElement; $n = $n->parentNode) {
			if ($n->localName === 'submission_file') {
				$name = '';
				foreach ($n->childNodes as $c) {
					if ($c instanceof DOMElement && $c->localName === 'name') { $name = trim($c->textContent); break; }
				}
				$label = sprintf('berkas #%s "%s"', $n->getAttribute('id'), $name);
				break;
			}
			if ($n->localName === 'issue_galley' || $n->localName === 'issue_cover' || $n->localName === 'cover') {
				$label = 'berkas ' . $n->localName;
				break;
			}
		}
		return $this->_articleLabel($embed) . ' › ' . $label;
	}

	/**
	 * @param $node DOMNode
	 * @return string
	 */
	protected function _articleLabel($node) {
		for ($n = $node; $n && $n instanceof DOMElement; $n = $n->parentNode) {
			if ($n->localName === 'article') {
				foreach ($n->childNodes as $c) {
					if ($c instanceof DOMElement && $c->localName === 'id' && $c->getAttribute('type') === 'internal') {
						return 'Artikel ' . trim($c->textContent);
					}
				}
				return 'Artikel';
			}
			if ($n->localName === 'issue') return 'Terbitan';
		}
		return 'XML';
	}

	/**
	 * @param $error string|null
	 * @return array
	 */
	protected function _result($error) {
		return [
			'findings' => $this->_findings,
			'skipped' => $this->_skipped,
			'scannedFiles' => $this->_scannedFiles,
			'error' => $error,
		];
	}
}

// CLI use: php JudolScanner.inc.php file.xml [file2.xml ...]
if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
	$exit = 0;
	foreach (array_slice($argv, 1) as $file) {
		$scanner = new JudolScanner();
		$r = $scanner->scanFile($file);
		echo "== $file\n";
		echo '   berkas tertanam dipindai: ' . $r['scannedFiles'] . "\n";
		if ($r['error']) echo '   catatan: ' . $r['error'] . "\n";
		foreach ($r['skipped'] as $s) echo '   tidak dipindai: ' . $s . "\n";
		if (empty($r['findings']) && $r['scannedFiles'] === 0 && $r['error'] === 'Berkas tidak dapat dibaca.') {
			$exit = 2;
			echo "   GAGAL: berkas tidak dapat dibaca.\n";
		} elseif (empty($r['findings'])) {
			echo "   BERSIH: tidak ada indikasi judol.\n";
		} else {
			$exit = 1;
			foreach ($r['findings'] as $f) {
				printf("   [%s] %s\n      \"%s\" — %s\n", strtoupper($f['kind']), $f['location'], $f['term'], $f['snippet']);
			}
		}
	}
	exit($exit);
}
