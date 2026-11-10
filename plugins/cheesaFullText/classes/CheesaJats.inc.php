<?php

/**
 * @file plugins/generic/cheesaFullText/classes/CheesaJats.inc.php
 *
 * CheesaJats — pengubah JATS XML menjadi HTML teks lengkap (tanpa library tambahan).
 *
 * Keamanan: HTML dibangun ulang dari daftar elemen yang diizinkan (whitelist). Elemen / atribut lain,
 * skrip, event handler, dan tautan javascript: tidak pernah ikut keluar. Entitas eksternal XML tidak dimuat.
 *
 * Hasil: ['html' => string, 'toc' => [[id, judul, level], ...], 'hasTex' => bool, 'figures' => int, 'tables' => int, 'refs' => int]
 */

class CheesaJats {

	const VERSION = '1.0';
	const XLINK = 'http://www.w3.org/1999/xlink';

	/** @var callable|null function(string $href): ?string — URL gambar dari nama berkas di <graphic xlink:href> */
	public $imageResolver = null;

	private $toc = [];
	private $hasTex = false;
	private $counts = ['fig' => 0, 'table' => 0, 'ref' => 0];
	private $usedIds = [];

	/** @return array|null null bila XML tidak terbaca / bukan JATS */
	public function convert($xml) {
		$xml = (string) $xml;
		if (trim($xml) === '') return null;
		// tolak DOCTYPE dengan entitas internal/eksternal (cegah XXE & "billion laughs")
		if (preg_match('/<!ENTITY/i', $xml)) $xml = preg_replace('/<!DOCTYPE[^>]*\[.*?\]\s*>/is', '', $xml);
		$xml = preg_replace('/<!DOCTYPE[^>\[]*>/i', '', $xml);
		$prev = libxml_use_internal_errors(true);
		if (PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) @libxml_disable_entity_loader(true);
		$doc = new DOMDocument();
		$ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		if (!$ok || !$doc->documentElement || $doc->documentElement->localName !== 'article') return null;

		$this->toc = []; $this->hasTex = false; $this->counts = ['fig' => 0, 'table' => 0, 'ref' => 0]; $this->usedIds = [];
		$root = $doc->documentElement;
		$body = $this->child($root, 'body');
		$back = $this->child($root, 'back');

		$html = '';
		if ($body) $html .= '<div class="cft-body">' . $this->renderChildren($body, 2) . '</div>';
		if ($back) $html .= $this->renderBack($back);
		if (trim(strip_tags($html, '<img><table>')) === '') return null;

		return [
			'html' => $html,
			'toc' => $this->toc,
			'hasTex' => $this->hasTex,
			'figures' => $this->counts['fig'],
			'tables' => $this->counts['table'],
			'refs' => $this->counts['ref'],
		];
	}

	/* ================= bagian belakang (ucapan terima kasih, lampiran, catatan, referensi) ================= */

	private function renderBack(DOMElement $back) {
		$out = '';
		foreach ($this->elements($back) as $n) {
			switch ($n->localName) {
				case 'ack':
					$out .= $this->section($n, 2, 'Acknowledgements', 'cft-ack');
					break;
				case 'app-group':
					foreach ($this->elements($n) as $app) if (in_array($app->localName, ['app', 'sec'], true)) $out .= $this->section($app, 2, 'Appendix', 'cft-app');
					break;
				case 'app':
				case 'sec':
					$out .= $this->section($n, 2, '', 'cft-app');
					break;
				case 'fn-group':
					$out .= $this->footnotes($n);
					break;
				case 'ref-list':
					$out .= $this->refList($n);
					break;
				case 'glossary':
					$out .= $this->section($n, 2, 'Glossary', 'cft-glossary');
					break;
			}
		}
		return $out;
	}

	private function refList(DOMElement $list) {
		$items = '';
		foreach ($this->elements($list) as $ref) {
			if ($ref->localName === 'ref-list') { $items .= ''; continue; }
			if ($ref->localName !== 'ref') continue;
			$this->counts['ref']++;
			$id = $this->id($ref->getAttribute('id') ?: 'ref' . $this->counts['ref'], 'cft-ref-');
			$label = $this->child($ref, 'label');
			$cit = null;
			foreach ($this->elements($ref) as $c) if (in_array($c->localName, ['mixed-citation', 'element-citation', 'citation', 'nlm-citation'], true)) { $cit = $c; break; }
			$text = $cit ? ($cit->localName === 'mixed-citation' || $cit->localName === 'citation' ? $this->inline($cit) : $this->elementCitation($cit)) : $this->inline($ref);
			$items .= '<li id="' . $id . '">' . ($label ? '<span class="cft-ref-label">' . $this->esc(trim($label->textContent)) . '</span> ' : '') . $text . '</li>';
		}
		if ($items === '') return '';
		$title = $this->child($list, 'title');
		$t = $title ? trim($title->textContent) : 'References';
		$sid = $this->id('references', 'cft-');
		$this->toc[] = [$sid, $t, 2];
		return '<section class="cft-refs" id="' . $sid . '"><h2>' . $this->esc($t) . '</h2><ol class="cft-ref-list">' . $items . '</ol></section>';
	}

	/** <element-citation>: susun ulang menjadi teks sitasi sederhana gaya APA */
	private function elementCitation(DOMElement $c) {
		$names = [];
		foreach ($c->getElementsByTagName('*') as $n) {
			if ($n->localName === 'name' || $n->localName === 'string-name') {
				$sn = $this->child($n, 'surname'); $gn = $this->child($n, 'given-names');
				$names[] = trim(($sn ? $sn->textContent : $n->textContent) . ($gn ? ', ' . $gn->textContent : ''));
			} elseif ($n->localName === 'collab') $names[] = trim($n->textContent);
		}
		$get = function ($tag) use ($c) { $e = $this->child($c, $tag); return $e ? $e : null; };
		$parts = [];
		if ($names) $parts[] = $this->esc(implode(', ', array_slice($names, 0, 6)) . (count($names) > 6 ? ', et al.' : '')) . '.';
		if ($y = $get('year')) $parts[] = '(' . $this->esc(trim($y->textContent)) . ').';
		if ($t = $get('article-title') ?: $get('chapter-title')) $parts[] = $this->inline($t) . '.';
		if ($s = $get('source')) {
			$src = '<i>' . $this->inline($s) . '</i>';
			if ($v = $get('volume')) $src .= ', <i>' . $this->esc(trim($v->textContent)) . '</i>';
			if ($i = $get('issue')) $src .= '(' . $this->esc(trim($i->textContent)) . ')';
			$fp = $get('fpage'); $lp = $get('lpage'); $el = $get('elocation-id');
			if ($fp) $src .= ', ' . $this->esc(trim($fp->textContent)) . ($lp ? '–' . $this->esc(trim($lp->textContent)) : '');
			elseif ($el) $src .= ', ' . $this->esc(trim($el->textContent));
			$parts[] = $src . '.';
		}
		if ($p = $get('publisher-name')) $parts[] = $this->esc(trim($p->textContent)) . '.';
		foreach ($this->elements($c) as $pid) {
			if ($pid->localName === 'pub-id' && strtolower($pid->getAttribute('pub-id-type')) === 'doi') $parts[] = $this->doiLink(trim($pid->textContent));
			elseif ($pid->localName === 'ext-link' || $pid->localName === 'uri') $parts[] = $this->inlineNode($pid);
		}
		return implode(' ', $parts);
	}

	private function footnotes(DOMElement $g) {
		$items = '';
		foreach ($this->elements($g) as $fn) {
			if ($fn->localName !== 'fn') continue;
			$id = $this->id($fn->getAttribute('id') ?: 'fn' . (count($this->usedIds) + 1), 'cft-fn-');
			$label = $this->child($fn, 'label');
			$ps = array_filter($this->elements($fn), function ($e) { return $e->localName !== 'label'; });
			$body = (count($ps) === 1 && reset($ps)->localName === 'p') ? $this->inline(reset($ps)) : $this->renderChildren($fn, 3, ['label']);
			$items .= '<li id="' . $id . '">' . ($label ? '<sup class="cft-fn-label">' . $this->esc(trim($label->textContent)) . '</sup> ' : '') . $body . '</li>';
		}
		if ($items === '') return '';
		$title = $this->child($g, 'title');
		return '<section class="cft-notes"><h2>' . $this->esc($title ? trim($title->textContent) : 'Notes') . '</h2><ul class="cft-fn-list">' . $items . '</ul></section>';
	}

	/* ================= blok ================= */

	private function section(DOMElement $sec, $level, $defaultTitle = '', $cls = 'cft-sec') {
		$title = $this->child($sec, 'title');
		$label = $this->child($sec, 'label');
		$t = $title ? $this->inline($title) : $this->esc($defaultTitle);
		$plain = trim(($label ? $label->textContent . ' ' : '') . ($title ? $title->textContent : $defaultTitle));
		$id = $this->id($sec->getAttribute('id') ?: $this->slug($plain ?: 'section'), 'cft-');
		$h = min(6, max(2, $level));
		if ($plain !== '' && $h <= 3) $this->toc[] = [$id, $plain, $h];
		$head = $t !== '' ? '<h' . $h . '>' . ($label ? '<span class="cft-num">' . $this->esc(trim($label->textContent)) . '</span> ' : '') . $t . '</h' . $h . '>' : '';
		return '<section class="' . $cls . '" id="' . $id . '">' . $head . $this->renderChildren($sec, $level + 1, ['title', 'label']) . '</section>';
	}

	private function renderChildren(DOMNode $parent, $level, $skip = []) {
		$out = '';
		foreach ($parent->childNodes as $n) {
			if ($n->nodeType === XML_TEXT_NODE) { if (trim($n->textContent) !== '') $out .= '<p>' . $this->esc($n->textContent) . '</p>'; continue; }
			if ($n->nodeType !== XML_ELEMENT_NODE || in_array($n->localName, $skip, true)) continue;
			$out .= $this->block($n, $level);
		}
		return $out;
	}

	private function block(DOMElement $n, $level) {
		switch ($n->localName) {
			case 'script': case 'style': case 'object': case 'embed': case 'iframe': return '';
			case 'sec': return $this->section($n, $level);
			case 'p': return $this->paragraph($n, $level);
			case 'list': return $this->listEl($n, $level);
			case 'fig': return $this->figure($n);
			case 'fig-group': return '<div class="cft-fig-group">' . $this->renderChildren($n, $level, ['caption', 'label']) . '</div>';
			case 'table-wrap': return $this->tableWrap($n);
			case 'table-wrap-group': return $this->renderChildren($n, $level);
			case 'disp-formula': return $this->dispFormula($n);
			case 'disp-formula-group': return $this->renderChildren($n, $level);
			case 'disp-quote': return '<blockquote>' . $this->renderChildren($n, $level) . '</blockquote>';
			case 'boxed-text': return '<aside class="cft-box">' . $this->renderChildren($n, $level) . '</aside>';
			case 'preformat':
			case 'code': return '<pre class="cft-pre">' . $this->esc($n->textContent) . '</pre>';
			case 'def-list': return $this->defList($n);
			case 'statement': return '<div class="cft-statement">' . $this->renderChildren($n, $level) . '</div>';
			case 'supplementary-material': return $this->supplementary($n);
			case 'graphic': return '<figure class="cft-fig">' . $this->graphic($n) . '</figure>';
			case 'title': return '<h' . min(6, $level) . '>' . $this->inline($n) . '</h' . min(6, $level) . '>';
			case 'caption': return '<div class="cft-caption">' . $this->renderChildren($n, $level) . '</div>';
			case 'fn-group': return $this->footnotes($n);
			case 'ref-list': return $this->refList($n);
			case 'ack': return $this->section($n, $level, 'Acknowledgements', 'cft-ack');
			case 'label': return '';
			case 'alternatives': $first = $this->elements($n); return $first ? $this->block($first[0], $level) : '';
			default:
				// elemen inline yang muncul di level blok
				return '<p>' . $this->inlineNode($n) . '</p>';
		}
	}

	/** <p> boleh berisi elemen blok (list, fig, tabel, rumus): pisahkan supaya HTML tetap sah */
	private function paragraph(DOMElement $p, $level) {
		$out = ''; $buf = '';
		$blocks = ['list', 'fig', 'table-wrap', 'disp-formula', 'disp-quote', 'boxed-text', 'preformat', 'def-list', 'fig-group', 'table-wrap-group', 'supplementary-material'];
		foreach ($p->childNodes as $c) {
			if ($c->nodeType === XML_ELEMENT_NODE && in_array($c->localName, $blocks, true)) {
				if (trim(strip_tags($buf)) !== '' || strpos($buf, '<img') !== false) $out .= '<p>' . $buf . '</p>';
				$buf = '';
				$out .= $this->block($c, $level);
			} else {
				$buf .= $this->inlineNode($c);
			}
		}
		if (trim(strip_tags($buf, '<img><math>')) !== '') $out .= '<p>' . $buf . '</p>';
		return $out;
	}

	private function listEl(DOMElement $l, $level) {
		$type = $l->getAttribute('list-type');
		$ordered = in_array($type, ['order', 'alpha-lower', 'alpha-upper', 'roman-lower', 'roman-upper'], true);
		$tag = $ordered ? 'ol' : 'ul';
		$styles = ['alpha-lower' => 'lower-alpha', 'alpha-upper' => 'upper-alpha', 'roman-lower' => 'lower-roman', 'roman-upper' => 'upper-roman', 'simple' => 'none'];
		$style = isset($styles[$type]) ? ' style="list-style-type:' . $styles[$type] . '"' : '';
		$items = '';
		foreach ($this->elements($l) as $li) {
			if ($li->localName !== 'list-item') continue;
			$label = $this->child($li, 'label');
			$items .= '<li>' . ($label ? '<span class="cft-li-label">' . $this->esc(trim($label->textContent)) . '</span> ' : '') . $this->listItemBody($li, $level) . '</li>';
		}
		return '<' . $tag . $style . '>' . $items . '</' . $tag . '>';
	}

	private function listItemBody(DOMElement $li, $level) {
		$els = array_filter($this->elements($li), function ($e) { return $e->localName !== 'label'; });
		// satu <p> saja: tampilkan tanpa pembungkus <p> agar rapat
		if (count($els) === 1 && reset($els)->localName === 'p') return $this->inline(reset($els));
		return $this->renderChildren($li, $level, ['label']);
	}

	private function defList(DOMElement $d) {
		$out = '';
		foreach ($this->elements($d) as $item) {
			if ($item->localName !== 'def-item') continue;
			$term = $this->child($item, 'term'); $def = $this->child($item, 'def');
			$out .= '<dt>' . ($term ? $this->inline($term) : '') . '</dt><dd>' . ($def ? $this->renderChildren($def, 4) : '') . '</dd>';
		}
		return '<dl class="cft-dl">' . $out . '</dl>';
	}

	private function figure(DOMElement $f) {
		$this->counts['fig']++;
		$id = $this->id($f->getAttribute('id') ?: 'fig' . $this->counts['fig'], 'cft-');
		$label = $this->child($f, 'label'); $cap = $this->child($f, 'caption');
		$imgs = '';
		foreach ($f->getElementsByTagName('*') as $g) if ($g->localName === 'graphic') $imgs .= $this->graphic($g, $label ? trim($label->textContent) : 'Figure');
		return '<figure class="cft-fig" id="' . $id . '">' . $imgs . '<figcaption>' . ($label ? '<b>' . $this->esc(trim($label->textContent)) . '</b> ' : '') . ($cap ? $this->captionInline($cap) : '') . '</figcaption></figure>';
	}

	private function graphic(DOMElement $g, $alt = 'Figure') {
		$href = $g->getAttributeNS(self::XLINK, 'href') ?: $g->getAttribute('xlink:href') ?: $g->getAttribute('href');
		$url = $this->imageUrl($href);
		if (!$url) return '<div class="cft-missing">[' . $this->esc($alt) . ': ' . $this->esc(basename($href)) . ']</div>';
		return '<a class="cft-zoom" href="' . $this->esc($url) . '" target="_blank" rel="noopener"><img src="' . $this->esc($url) . '" alt="' . $this->esc($alt) . '" loading="lazy"></a>';
	}

	private function imageUrl($href) {
		$href = trim((string) $href);
		if ($href === '') return null;
		if ($this->imageResolver) { $u = call_user_func($this->imageResolver, $href); if ($u) return $u; }
		return preg_match('#^https://#i', $href) ? $href : null;
	}

	private function tableWrap(DOMElement $t) {
		$this->counts['table']++;
		$id = $this->id($t->getAttribute('id') ?: 'table' . $this->counts['table'], 'cft-');
		$label = $this->child($t, 'label'); $cap = $this->child($t, 'caption');
		$table = '';
		foreach ($t->getElementsByTagName('*') as $tb) { if ($tb->localName === 'table') { $table = $this->table($tb); break; } }
		if ($table === '') foreach ($this->elements($t) as $g) if ($g->localName === 'graphic') $table = $this->graphic($g, $label ? trim($label->textContent) : 'Table');
		$foot = '';
		foreach ($this->elements($t) as $f) if ($f->localName === 'table-wrap-foot') $foot .= '<div class="cft-table-foot">' . $this->renderChildren($f, 4) . '</div>';
		return '<figure class="cft-table" id="' . $id . '"><figcaption>' . ($label ? '<b>' . $this->esc(trim($label->textContent)) . '</b> ' : '') . ($cap ? $this->captionInline($cap) : '') . '</figcaption><div class="cft-table-scroll">' . $table . '</div>' . $foot . '</figure>';
	}

	private function table(DOMElement $t) {
		$allowed = ['table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'col', 'colgroup', 'caption'];
		$build = function (DOMElement $e) use (&$build, $allowed) {
			$out = '';
			foreach ($e->childNodes as $c) {
				if ($c->nodeType === XML_TEXT_NODE) { $out .= $this->esc($c->textContent); continue; }
				if ($c->nodeType !== XML_ELEMENT_NODE) continue;
				if (in_array($c->localName, $allowed, true)) {
					$attrs = '';
					foreach (['colspan', 'rowspan', 'align', 'valign', 'span', 'scope'] as $a) {
						$v = $c->getAttribute($a);
						if ($v !== '' && preg_match('/^[a-z0-9-]{1,10}$/i', $v)) $attrs .= ' ' . $a . '="' . $this->esc($v) . '"';
					}
					$out .= in_array($c->localName, ['col'], true) ? '<col' . $attrs . '>' : '<' . $c->localName . $attrs . '>' . (in_array($c->localName, ['th', 'td'], true) ? $this->cell($c) : $build($c)) . '</' . $c->localName . '>';
				} else {
					$out .= $this->inlineNode($c);
				}
			}
			return $out;
		};
		return '<table>' . $build($t) . '</table>';
	}

	private function cell(DOMElement $c) {
		$hasBlock = false;
		foreach ($this->elements($c) as $e) if (in_array($e->localName, ['p', 'list'], true)) $hasBlock = true;
		return $hasBlock ? $this->renderChildren($c, 5) : $this->inline($c);
	}

	private function captionInline(DOMElement $cap) {
		$parts = [];
		foreach ($this->elements($cap) as $e) $parts[] = $e->localName === 'title' ? '<b>' . $this->inline($e) . '</b>' : $this->inline($e);
		return $parts ? implode(' ', $parts) : $this->inline($cap);
	}

	private function dispFormula(DOMElement $f) {
		$id = $f->getAttribute('id') ? ' id="' . $this->id($f->getAttribute('id'), 'cft-') . '"' : '';
		$label = $this->child($f, 'label');
		return '<div class="cft-formula"' . $id . '><div class="cft-formula-body">' . $this->formulaBody($f, true) . '</div>' . ($label ? '<span class="cft-formula-label">' . $this->esc(trim($label->textContent)) . '</span>' : '') . '</div>';
	}

	private function formulaBody(DOMElement $f, $display) {
		$alts = $this->child($f, 'alternatives') ?: $f;
		foreach ($this->elements($alts) as $e) if ($e->localName === 'math') return $this->math($e, $display);
		foreach ($this->elements($alts) as $e) if ($e->localName === 'tex-math') {
			$this->hasTex = true;
			$tex = trim(preg_replace('/^\\\\begin\{document\}|\\\\end\{document\}$/', '', trim($e->textContent)));
			$tex = trim($tex, " \$\n\r\t");
			return $display ? '\\[' . $this->esc($tex) . '\\]' : '\\(' . $this->esc($tex) . '\\)';
		}
		foreach ($this->elements($alts) as $e) if (in_array($e->localName, ['graphic', 'inline-graphic'], true)) return $this->graphic($e, 'Formula');
		return $this->inline($f);
	}

	private function supplementary(DOMElement $s) {
		$href = $s->getAttributeNS(self::XLINK, 'href') ?: $s->getAttribute('xlink:href');
		$url = $this->imageUrl($href);
		$label = $this->child($s, 'label'); $cap = $this->child($s, 'caption');
		$txt = trim(($label ? $label->textContent : 'Supplementary material') . ($cap ? ' — ' . $cap->textContent : ''));
		return '<p class="cft-supp">' . ($url ? '<a href="' . $this->esc($url) . '" target="_blank" rel="noopener">' . $this->esc($txt) . '</a>' : $this->esc($txt)) . '</p>';
	}

	/* ================= inline ================= */

	private function inline(DOMNode $n) {
		$out = '';
		foreach ($n->childNodes as $c) $out .= $this->inlineNode($c);
		return trim($out);
	}

	private function inlineNode(DOMNode $c) {
		if ($c->nodeType === XML_TEXT_NODE || $c->nodeType === XML_CDATA_SECTION_NODE) return $this->esc($c->textContent);
		if ($c->nodeType !== XML_ELEMENT_NODE) return '';
		if (in_array(strtolower($c->localName), ['script', 'style', 'object', 'embed', 'iframe', 'processing-meta', 'alt-text', 'long-desc'], true)) return '';
		$map = ['bold' => 'b', 'italic' => 'i', 'underline' => 'u', 'sup' => 'sup', 'sub' => 'sub', 'monospace' => 'code', 'strike' => 's', 'sc' => 'span class="cft-sc"', 'overline' => 'span class="cft-over"', 'roman' => 'span', 'sans-serif' => 'span'];
		$name = $c->localName;
		if (isset($map[$name])) { $tag = explode(' ', $map[$name])[0]; return '<' . $map[$name] . '>' . $this->inline($c) . '</' . $tag . '>'; }
		switch ($name) {
			case 'break': return '<br>';
			case 'ext-link':
			case 'uri':
				$href = $c->getAttributeNS(self::XLINK, 'href') ?: $c->getAttribute('xlink:href') ?: trim($c->textContent);
				$url = $this->safeUrl($href);
				$txt = $this->inline($c) ?: $this->esc($href);
				return $url ? '<a href="' . $this->esc($url) . '" target="_blank" rel="noopener">' . $txt . '</a>' : $txt;
			case 'email':
				$m = trim($c->textContent);
				return filter_var($m, FILTER_VALIDATE_EMAIL) ? '<a href="mailto:' . $this->esc($m) . '">' . $this->esc($m) . '</a>' : $this->esc($m);
			case 'xref':
				$rid = trim(explode(' ', trim($c->getAttribute('rid')))[0]);
				$type = $c->getAttribute('ref-type');
				$prefix = $type === 'bibr' ? 'cft-ref-' : ($type === 'fn' ? 'cft-fn-' : 'cft-');
				$txt = $this->inline($c);
				if ($rid === '') return $txt;
				return '<a class="cft-xref cft-xref-' . $this->esc($type ?: 'other') . '" href="#' . $this->esc($prefix . $this->clean($rid)) . '">' . $txt . '</a>';
			case 'pub-id':
				return strtolower($c->getAttribute('pub-id-type')) === 'doi' ? $this->doiLink(trim($c->textContent)) : $this->esc($c->textContent);
			case 'inline-formula': return '<span class="cft-iformula">' . $this->formulaBody($c, false) . '</span>';
			case 'math': return $this->math($c, false);
			case 'tex-math': $this->hasTex = true; return '\\(' . $this->esc(trim($c->textContent, " \$\n\r\t")) . '\\)';
			case 'inline-graphic': return $this->graphic($c, 'Image');
			case 'fn': return '';
			case 'named-content':
			case 'styled-content':
			case 'abbrev':
			case 'span':
			case 'target':
			case 'kwd':
			case 'string-name':
			case 'name':
			case 'surname':
			case 'given-names':
			case 'source':
			case 'article-title':
			case 'year':
			case 'volume':
			case 'issue':
			case 'fpage':
			case 'lpage':
			case 'person-group':
			case 'etal':
			case 'collab':
			case 'publisher-name':
			case 'publisher-loc':
			case 'chapter-title':
			case 'edition':
			case 'comment':
			case 'label':
			case 'title':
			case 'p':
				return $this->inline($c) . ($name === 'p' ? ' ' : '');
			default:
				return $this->inline($c);
		}
	}

	/* ================= MathML (whitelist) ================= */

	private function math(DOMElement $m, $display) {
		$tags = ['math', 'mi', 'mo', 'mn', 'ms', 'mtext', 'mspace', 'mrow', 'msup', 'msub', 'msubsup', 'mfrac', 'msqrt', 'mroot', 'mstyle', 'mfenced', 'menclose',
			'mpadded', 'mphantom', 'mtable', 'mtr', 'mtd', 'mlabeledtr', 'munder', 'mover', 'munderover', 'mmultiscripts', 'mprescripts', 'none', 'semantics', 'merror'];
		$attrs = ['mathvariant', 'stretchy', 'fence', 'separator', 'accent', 'accentunder', 'columnalign', 'rowalign', 'linethickness', 'open', 'close', 'separators',
			'notation', 'lspace', 'rspace', 'largeop', 'movablelimits', 'symmetric', 'width', 'height', 'depth', 'displaystyle', 'scriptlevel', 'columnspan', 'rowspan', 'form'];
		$build = function (DOMNode $n) use (&$build, $tags, $attrs, $display) {
			if ($n->nodeType === XML_TEXT_NODE) return $this->esc($n->textContent);
			if ($n->nodeType !== XML_ELEMENT_NODE) return '';
			$name = $n->localName;
			if ($name === 'annotation' || $name === 'annotation-xml') return '';
			$inner = '';
			foreach ($n->childNodes as $c) $inner .= $build($c);
			if (!in_array($name, $tags, true)) return $inner;
			$a = '';
			foreach ($attrs as $at) { $v = $n->getAttribute($at); if ($v !== '' && preg_match('/^[\w .,%()#-]{1,40}$/u', $v)) $a .= ' ' . $at . '="' . $this->esc($v) . '"'; }
			if ($name === 'math') $a = ' xmlns="http://www.w3.org/1998/Math/MathML"' . ($display ? ' display="block"' : '') . $a;
			return '<' . $name . $a . '>' . $inner . '</' . $name . '>';
		};
		return $build($m);
	}

	/* ================= utilitas ================= */

	private function doiLink($doi) {
		$doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', trim($doi));
		if (!preg_match('#^10\.\d{4,9}/\S+$#', $doi)) return $this->esc($doi);
		return '<a href="https://doi.org/' . $this->esc($doi) . '" target="_blank" rel="noopener">https://doi.org/' . $this->esc($doi) . '</a>';
	}

	private function safeUrl($u) {
		$u = trim((string) $u);
		if (preg_match('#^10\.\d{4,9}/#', $u)) return 'https://doi.org/' . $u;
		if (preg_match('#^www\.#i', $u)) $u = 'https://' . $u;
		return preg_match('#^(https?://|mailto:)#i', $u) ? $u : null;
	}

	private function child(DOMNode $p, $name) {
		foreach ($p->childNodes as $c) if ($c->nodeType === XML_ELEMENT_NODE && $c->localName === $name) return $c;
		return null;
	}

	/** @return DOMElement[] */
	private function elements(DOMNode $p) {
		$out = [];
		foreach ($p->childNodes as $c) if ($c->nodeType === XML_ELEMENT_NODE) $out[] = $c;
		return $out;
	}

	private function clean($id) { return preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $id); }

	private function slug($t) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), '-') ?: 'section'; }

	/** id unik & aman; id asli JATS dipertahankan supaya <xref rid> tetap cocok */
	private function id($raw, $prefix) {
		$id = $prefix . $this->clean($raw);
		$base = $id; $i = 2;
		while (isset($this->usedIds[$id])) $id = $base . '-' . $i++;
		$this->usedIds[$id] = true;
		return $id;
	}

	public function esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
