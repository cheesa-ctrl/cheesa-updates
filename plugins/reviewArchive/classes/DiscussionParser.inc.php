<?php

/**
 * @file plugins/generic/reviewArchive/classes/DiscussionParser.inc.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class DiscussionParser
 * @ingroup plugins_generic_reviewArchive
 *
 * @brief Parse the HTML of an OJS "Review Discussion" modal (the
 *        query-notes grid) into a structured thread. Used for migrated
 *        articles whose discussions did not travel with the Native XML
 *        import: an editor pastes the discussion HTML from the old OJS and it
 *        is rendered in the Review Archive tab like a live discussion.
 *
 * Everything is defensive: unknown or malformed markup yields an empty list
 * rather than an error.
 */

class DiscussionParser {

	/**
	 * Parse one or MORE pasted discussion panels. Each OJS discussion appears
	 * as a `.pkp_controllers_query` container; every one becomes its own
	 * thread, so an editor can paste several discussions at once. If no such
	 * container is found, the whole paste is treated as a single thread.
	 *
	 * @param $html string the pasted discussion HTML
	 * @return array of ['title','participants'=>[],'messages'=>[['from','date','title','body','attachments'=>[['name','url']]]]]
	 */
	public static function parse($html) {
		$html = trim((string) $html);
		if ($html === '' || !class_exists('DOMDocument')) return [];

		// Scripts carry no thread content and only add noise/risk.
		$html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);

		$prev = libxml_use_internal_errors(true);
		$doc = new DOMDocument();
		$ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		if (!$ok) return [];

		$xp = new DOMXPath($doc);
		$threads = [];

		// 1) Full discussion panel(s): the modal with Participants + Messages.
		$containers = $xp->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' pkp_controllers_query ')]");
		if ($containers->length) {
			foreach ($containers as $container) {
				$t = self::_extractThread($xp, $container);
				if ($t) $threads[] = $t;
			}
		}
		if ($threads) return $threads;

		// 2) Discussion LIST (the queries grid): rows that link to each
		//    discussion but carry no messages. Rendered as an index.
		$list = self::_parseList($xp);
		if ($list) return $list;

		// 3) Fallback: a bare notes grid without its container wrapper.
		$t = self::_extractThread($xp, $doc->documentElement);
		return $t ? [$t] : [];
	}

	/**
	 * Parse the "Review Discussions" list grid into index entries (title,
	 * creator, date, replies, closed, link) — no messages are present here.
	 * @param $xp DOMXPath
	 * @return array
	 */
	protected static function _parseList($xp) {
		$entries = [];
		foreach ($xp->query("//a[contains(@class,'pkp_linkaction_readQuery')]") as $a) {
			$title = self::_clean($a->textContent);
			if ($title === '') continue;
			$href = trim($a->getAttribute('href'));
			$queryId = null;
			if (preg_match('/[?&]queryId=(\d+)/', $href, $qm)) $queryId = $qm[1];
			$rowSet = $xp->query("ancestor::tr[contains(concat(' ', normalize-space(@class), ' '), ' gridRow ')][1]", $a);
			$from = '';
			$date = '';
			$replies = '';
			$closed = false;
			if ($rowSet->length) {
				$row = $rowSet->item(0);
				$fromCell = $xp->query(".//span[contains(@id,'-from')]", $row);
				if ($fromCell->length) {
					$lines = preg_split('/\n+/', self::_nodeText($fromCell->item(0)));
					$from = self::_clean($lines[0] ?? '');
					$date = self::_clean(implode(' ', array_slice($lines, 1)));
				}
				$repCell = $xp->query(".//span[contains(@id,'-replies')]", $row);
				if ($repCell->length) $replies = self::_clean($repCell->item(0)->textContent);
				$chk = $xp->query(".//input[@type='checkbox']", $row);
				if ($chk->length) $closed = $chk->item(0)->hasAttribute('checked');
			}
			$entries[] = [
				'title' => $title,
				'participants' => [],
				'messages' => [],
				'indexOnly' => true,
				'queryId' => $queryId,
				'link' => ($href !== '' && $href !== '#') ? $href : '',
				'from' => $from,
				'date' => $date,
				'replies' => $replies,
				'closed' => $closed,
			];
		}
		return $entries;
	}

	/**
	 * Extract one thread (title, participants, messages) from a scope node.
	 * @param $xp DOMXPath
	 * @param $scope DOMNode the discussion container (or document element)
	 * @return array|null
	 */
	protected static function _extractThread($xp, $scope) {
		$title = self::_titleFor($xp, $scope);

		// Stage label, if the capture script embedded one (data-ra-stage).
		$stage = '';
		if ($scope && $scope->nodeType === XML_ELEMENT_NODE) {
			$anc = $xp->query("ancestor-or-self::*[@data-ra-stage][1]", $scope);
			if ($anc->length) $stage = trim($anc->item(0)->getAttribute('data-ra-stage'));
		}

		$participants = [];
		foreach ($xp->query(".//ul[contains(@class,'participants')]/li", $scope) as $li) {
			$t = self::_clean($li->textContent);
			if ($t !== '') $participants[] = $t;
		}

		$messages = [];
		foreach ($xp->query(".//tr[contains(@class,'gridRow')]", $scope) as $tr) {
			if (strpos($tr->getAttribute('class'), 'row_controls') !== false) continue;
			$tds = $tr->getElementsByTagName('td');
			if ($tds->length < 1) continue;

			$contentsTd = null;
			foreach ($tds as $td) {
				if (strpos($td->getAttribute('class'), 'first_column') !== false) { $contentsTd = $td; break; }
			}
			if ($contentsTd === null) $contentsTd = $tds->item(0);
			$fromTd = $tds->item($tds->length - 1);

			$atts = [];
			foreach ($xp->query(".//a[contains(@class,'downloadFile') or contains(@href,'download-file')]", $contentsTd) as $a) {
				$name = self::_clean($a->textContent);
				$href = trim($a->getAttribute('href'));
				// Keep the file even when the link has no usable URL (href="#"):
				// the name is still worth archiving; it just shows without a link.
				if ($name !== '') $atts[] = ['name' => $name, 'url' => ($href !== '' && $href !== '#') ? $href : ''];
			}

			$body = '';
			$labels = $xp->query(".//span[contains(@class,'label')]", $contentsTd);
			if ($labels->length) $body = self::_nodeText($labels->item(0));

			$from = '';
			$date = '';
			if ($fromTd) {
				$fl = $xp->query(".//span[contains(@class,'label')]", $fromTd);
				if ($fl->length) {
					$lines = preg_split('/\n+/', self::_nodeText($fl->item(0)));
					$from = self::_clean($lines[0] ?? '');
					$date = self::_clean(implode(' ', array_slice($lines, 1)));
				}
			}

			if ($body === '' && !$atts && $from === '') continue;
			$messages[] = ['from' => $from, 'date' => $date, 'title' => '', 'body' => $body, 'attachments' => $atts];
		}

		if (!$participants && !$messages) return null;
		return [
			'title' => $title !== '' ? $title : 'Diskusi (arsip)',
			'stage' => $stage,
			'participants' => $participants,
			'messages' => $messages,
		];
	}

	/**
	 * Find a discussion's title: the header of its enclosing modal panel, else
	 * the nearest header before it in document order.
	 * @param $xp DOMXPath
	 * @param $node DOMNode
	 * @return string
	 */
	protected static function _titleFor($xp, $node) {
		$headerClass = "contains(concat(' ', normalize-space(@class), ' '), ' header ')";
		$panel = $xp->query("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' pkp_modal_panel ')][1]", $node);
		if ($panel->length) {
			$h = $xp->query(".//div[$headerClass]", $panel->item(0));
			if ($h->length) return self::_clean($h->item(0)->textContent);
		}
		$h = $xp->query("preceding::div[$headerClass][1]", $node);
		if ($h->length) return self::_clean($h->item(0)->textContent);
		return '';
	}

	/**
	 * Inner text of a node with <br>/<p> turned into line breaks and any
	 * download anchors removed (they are surfaced as attachments instead).
	 * @param $node DOMNode
	 * @return string
	 */
	protected static function _nodeText($node) {
		$doc = $node->ownerDocument;
		$html = '';
		foreach ($node->childNodes as $c) $html .= $doc->saveHTML($c);
		$html = preg_replace('#<a\b[^>]*(?:pkp_linkaction_downloadFile|download-file)[^>]*>.*?</a>#is', '', $html);
		$html = preg_replace('#<\s*(br|/p|/div|/li)\b[^>]*>#i', "\n", $html);
		$txt = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$txt = str_replace("\xC2\xA0", ' ', $txt); // non-breaking space
		$txt = preg_replace("/[ \t]+\n/", "\n", $txt);
		$txt = preg_replace("/\n{3,}/", "\n\n", $txt);
		return trim($txt);
	}

	/**
	 * Collapse whitespace.
	 * @param $s string
	 * @return string
	 */
	protected static function _clean($s) {
		$s = str_replace("\xC2\xA0", ' ', (string) $s);
		return trim(preg_replace('/\s+/u', ' ', $s));
	}
}
