<?php

/**
 * @file plugins/generic/reviewArchive/classes/TextExtractor.inc.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class TextExtractor
 * @ingroup plugins_generic_reviewArchive
 *
 * @brief Pulls readable text out of the files embedded in OJS Native XML
 *  (.pdf, .doc, .docx/.odt, .html, .txt) without external tools.
 *  Used by JudolScanner and ReviewArchiveXmlProcessor. No OJS dependencies.
 */

class TextExtractor {

	/**
	 * Readable text of a file.
	 * @param $data string file bytes
	 * @param $extension string
	 * @return array [type, text|null] text is null when nothing can be read
	 */
	public function extract($data, $extension) {
		$type = $this->_detectType($data, strtolower((string) $extension));
		switch ($type) {
			case 'pdf': return [$type, $this->_pdfText($data)];
			case 'zip': return [$type, $this->_zipText($data)];
			case 'rar': case '7z': case 'image': case 'media': return [$type, null];
			case 'text': return [$type, $data];
			default: return [$type, $this->_binaryText($data)];
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
				if (strpos($content, '<w:t') !== false) {
					// WordprocessingML: only the <w:t> runs, one line per paragraph
					// (drawing positions and other numbers in tags are skipped).
					$withBreaks = preg_replace('/<\/w:p>|<w:br\s*\/?>|<w:cr\s*\/?>/', "\n", $content);
					$withBreaks = preg_replace('/<w:tab\s*\/>/', "\t", $withBreaks);
					preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>|(\n)|(\t)/s', $withBreaks, $runs, PREG_SET_ORDER);
					$plain = '';
					foreach ($runs as $run) {
						$plain .= isset($run[2]) && $run[2] !== '' ? "\n" : (isset($run[3]) && $run[3] !== '' ? ' ' : $run[1]);
					}
					$text[] = html_entity_decode($plain, ENT_QUOTES | ENT_XML1, 'UTF-8');
				} else {
					$text[] = html_entity_decode(strip_tags(preg_replace('/<\/w:p>|<\/text:p>|<br\s*\/?>/i', "\n", $content)), ENT_QUOTES | ENT_XML1, 'UTF-8');
				}
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
}
