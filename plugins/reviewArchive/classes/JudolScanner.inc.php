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

class JudolScanner {

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

	/** @var array */
	protected $_findings = [];

	/** @var array Locations that could not be scanned (e.g. .rar). */
	protected $_skipped = [];

	/** @var int */
	protected $_scannedFiles = 0;

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
	// Text extraction
	//
	/**
	 * @param $data string
	 * @param $extension string
	 * @return string
	 */
	protected function _detectType($data, $extension) {
		$head = substr($data, 0, 8);
		if (strpos($head, '%PDF') === 0) return 'pdf';
		if (strpos($head, "PK\x03\x04") === 0) return 'zip';
		if (strpos($head, 'Rar!') === 0) return 'rar';
		if (strpos($head, "7z\xBC\xAF") === 0) return '7z';
		if (strpos($head, "\xFF\xD8\xFF") === 0 || strpos($head, "\x89PNG") === 0 || strpos($head, 'GIF8') === 0) return 'image';
		if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff', 'bmp'])) return 'image';
		if (in_array($extension, ['mp3', 'mp4', 'wav', 'avi', 'mov', 'mkv', 'webm'])) return 'media';
		if (in_array($extension, ['html', 'htm', 'xml', 'txt', 'csv', 'md', 'jats', 'json', 'svg'])) return 'text';
		return 'binary';
	}

	/**
	 * Text and link targets from a PDF: inflate the streams and collect the
	 * string operands (...) and hex strings <...> of the text operators.
	 * @param $pdf string
	 * @return string
	 */
	protected function _pdfText($pdf) {
		$chunks = [];
		if (preg_match_all('/stream\r?\n(.*?)\r?\n?endstream/s', $pdf, $matches)) {
			foreach ($matches[1] as $stream) {
				$inflated = @gzuncompress($stream);
				if ($inflated === false) $inflated = @gzinflate($stream);
				if ($inflated === false) $inflated = @zlib_decode($stream);
				$chunks[] = $inflated !== false ? $inflated : '';
			}
		}
		$chunks[] = preg_replace('/stream\r?\n.*?endstream/s', ' ', $pdf); // objects outside streams (/URI etc.)

		$text = [];
		foreach ($chunks as $chunk) {
			if ($chunk === '') continue;
			// Literal strings, joined per TJ array so kerned words stay whole.
			if (preg_match_all('/\[(.*?)\]\s*TJ/s', $chunk, $arrays)) {
				foreach ($arrays[1] as $array) {
					preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $array, $parts);
					$text[] = implode('', array_map([$this, '_pdfLiteral'], $parts[0]));
				}
			}
			if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*(Tj|\'|")/s', $chunk, $singles)) {
				foreach ($singles[0] as $single) {
					$text[] = $this->_pdfLiteral(preg_replace('/\s*(Tj|\'|")$/', '', $single));
				}
			}
			// Hex strings <...>
			if (preg_match_all('/<([0-9A-Fa-f\s]{4,})>/', $chunk, $hex)) {
				foreach ($hex[1] as $h) {
					$bin = @hex2bin(preg_replace('/\s+/', '', strlen(preg_replace('/\s+/', '', $h)) % 2 ? $h . '0' : $h));
					if ($bin !== false) $text[] = $this->_binaryText($bin);
				}
			}
			// /URI (...) link targets and other plain words.
			if (preg_match_all('/\/URI\s*\((.*?)\)/s', $chunk, $uris)) {
				foreach ($uris[1] as $uri) $text[] = ' ' . $uri . ' ';
			}
		}
		return implode("\n", $text);
	}

	/**
	 * Decode a PDF literal string "(...)".
	 * @param $literal string
	 * @return string
	 */
	protected function _pdfLiteral($literal) {
		$s = substr($literal, 1, -1);
		$s = preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', function($m) {
			$c = $m[1];
			if (ctype_digit($c)) return chr(octdec($c) & 0xFF);
			$map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => '', 'f' => ''];
			return $map[$c] ?? $c;
		}, $s);
		// Keep every printable character: kerned PDFs split words into tiny
		// pieces ("(ban)" -20 "(dar)"), so no minimum run length here.
		return preg_replace('/[^\x20-\x7E\xA0-\xFF\t\r\n]/', '', $s);
	}

	/**
	 * Text inside an Office Open XML / ODF zip (document text, links, comments).
	 * @param $data string
	 * @return string|null null when ZipArchive is unavailable.
	 */
	protected function _zipText($data) {
		if (!class_exists('ZipArchive')) return null;
		$tmp = tempnam(sys_get_temp_dir(), 'judol');
		if ($tmp === false) return null;
		file_put_contents($tmp, $data);
		$zip = new ZipArchive();
		$text = [];
		if ($zip->open($tmp) === true) {
			for ($i = 0; $i < $zip->numFiles && $i < 500; $i++) {
				$name = $zip->getNameIndex($i);
				if (!preg_match('/\.(xml|rels|html?|txt)$/i', $name)) continue;
				$stat = $zip->statIndex($i);
				if ($stat && $stat['size'] > 20 * 1024 * 1024) continue;
				$content = $zip->getFromIndex($i);
				if ($content === false) continue;
				// Keep hyperlink targets, then drop the tags.
				if (preg_match_all('/Target="([^"]+)"/', $content, $targets)) {
					$text[] = implode(' ', $targets[1]);
				}
				$text[] = html_entity_decode(strip_tags(preg_replace('/<\/w:p>|<\/text:p>|<br\s*\/?>/i', "\n", $content)), ENT_QUOTES | ENT_XML1, 'UTF-8');
			}
			$zip->close();
		} else {
			$text = null;
		}
		@unlink($tmp);
		return $text === null ? null : implode("\n", $text);
	}

	/**
	 * Readable text from a binary file (.doc, .rtf ...): both 8-bit runs and
	 * UTF-16LE runs, which is how Word 97-2003 stores most text.
	 * @param $data string
	 * @return string
	 */
	protected function _binaryText($data) {
		$out = [];
		if (preg_match_all('/[\x20-\x7E\xA0-\xFF\t\r\n]{4,}/', $data, $m)) {
			$out[] = implode("\n", $m[0]);
		}
		if (preg_match_all('/(?:[\x20-\x7E\t\r\n]\x00){4,}/', $data, $m)) {
			$out[] = implode("\n", array_map(function($s) { return str_replace("\x00", '', $s); }, $m[0]));
		}
		return implode("\n", $out);
	}

	/**
	 * Remove long base64 runs so a broken XML can still be scanned as text.
	 * @param $xml string
	 * @return string
	 */
	protected function _stripBase64($xml) {
		return preg_replace('/[A-Za-z0-9+\/=\r\n]{500,}/', ' ', $xml);
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
