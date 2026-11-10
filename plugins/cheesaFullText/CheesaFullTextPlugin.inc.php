<?php

/**
 * @file plugins/generic/cheesaFullText/CheesaFullTextPlugin.inc.php
 *
 * CHEESA Full Text (OJS 3.3)
 * Naskah JATS XML yang diunggah sebagai galley ditampilkan sebagai teks lengkap HTML:
 *  - di halaman artikel (bagian "Full Text", menjadi tab di CHEESA Theme), dan
 *  - di halaman baca tersendiri saat tombol galley XML diklik (bukan mengunduh berkas mentah).
 * Gambar diambil dari berkas pendukung (dependent files) galley XML. Hasil konversi disimpan di cache.
 * Akses mengikuti aturan OJS: teks lengkap hanya tampil bila pembaca boleh mengakses galley.
 */

import('lib.pkp.classes.plugins.GenericPlugin');
require_once(__DIR__ . '/classes/CheesaJats.inc.php');
require_once(__DIR__ . '/classes/CheesaLicense.inc.php');

class CheesaFullTextPlugin extends GenericPlugin {

	const XML_TYPES = ['application/xml', 'text/xml', 'application/jats+xml'];

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId) && !CheesaLicense::checkRequest(__DIR__)) {
			HookRegistry::register('ArticleHandler::view', [$this, 'onArticleView']);
			HookRegistry::register('Templates::Article::Main', [$this, 'onArticleMain']);
			HookRegistry::register('ArticleHandler::view::galley', [$this, 'onGalleyView']);
		}
		return $success;
	}

	public function getDisplayName() { return 'CHEESA Full Text'; }

	public function getDescription() {
		return 'Menampilkan naskah JATS XML (galley XML) sebagai teks lengkap HTML di halaman artikel dan di halaman baca tersendiri, lengkap dengan daftar isi, gambar, tabel, rumus, dan referensi.'
			. CheesaLicense::notice(CheesaLicense::checkRequest(__DIR__));
	}

	/* ---------------- hook ---------------- */

	/** Halaman artikel: muat CSS/JS hanya bila ada galley XML. */
	public function onArticleView($hookName, $args) {
		$request = $args[0];
		$publication = $args[3] ?? null;
		if ($publication && $this->xmlGalley($publication)) $this->addAssets($request);
		return false;
	}

	/** Isi bagian "Full Text" di kolom utama halaman artikel. */
	public function onArticleMain($hookName, $params) {
		$smarty = $params[1];
		$output =& $params[2];
		if ($smarty->getTemplateVars('hasAccess') === false) return false;    // berlangganan/berbayar: ikuti aturan akses OJS
		$article = $smarty->getTemplateVars('article');
		$publication = $smarty->getTemplateVars('publication');
		if (!$article || !$publication) return false;
		$galley = $this->xmlGalley($publication);
		if (!$galley) return false;
		$request = Application::get()->getRequest();
		$r = $this->render($request, $article, $galley);
		if (!$r) return false;
		$readUrl = $request->url(null, 'article', 'view', [$article->getBestId(), $galley->getBestGalleyId()]);
		$output .= '<section class="item fulltext" id="cft-fulltext"><h2 class="label">Full Text</h2>'
			. $this->wrap($r, $readUrl) . '</section>';
		return false;
	}

	/** Tombol galley XML: tampilkan halaman baca, bukan mengunduh berkas XML. */
	public function onGalleyView($hookName, $args) {
		$request = $args[0];
		$issue = $args[1];
		$galley = $args[2];
		$article = $args[3];
		$publication = $args[4] ?? null;
		if (!$galley || !$this->isXmlGalley($galley) || $request->getUserVar('download')) return false;
		$r = $this->render($request, $article, $galley);
		if (!$r) return false;   // XML tidak bisa dibaca: biarkan OJS mengunduh berkasnya
		$this->addAssets($request);
		$tm = TemplateManager::getManager($request);
		$downloadUrl = $request->url(null, 'article', 'download', [$article->getBestId(), $galley->getBestGalleyId()]);
		$pub = $publication ?: $article->getCurrentPublication();
		$authors = [];
		foreach ((array) $pub->getData('authors') as $a) $authors[] = $a->getFullName();
		$tm->assign([
			'cftAuthors' => implode(', ', $authors),
			'cftArticle' => $article,
			'cftPublication' => $publication ?: $article->getCurrentPublication(),
			'cftIssue' => $issue,
			'cftHtml' => $this->wrap($r, null),
			'cftBackUrl' => $request->url(null, 'article', 'view', [$article->getBestId()]),
			'cftDownloadUrl' => $downloadUrl,
			'cftPdfUrl' => $this->pdfUrl($request, $article, $publication ?: $article->getCurrentPublication()),
		]);
		$tm->display($this->getTemplateResource('galley.tpl'));
		return true;
	}

	/* ---------------- konversi + cache ---------------- */

	/** @return array|null hasil CheesaJats::convert */
	private function render($request, $article, $galley) {
		$file = $galley->getFile();
		if (!$file) return null;
		$dir = rtrim(Config::getVar('files', 'files_dir'), '/\\') . '/cheesaFullText/cache';
		$key = (int) $file->getId() . '-' . md5(implode('|', [$file->getData('path'), $file->getData('updatedAt'), $galley->getId(), $article->getBestId(), CheesaJats::VERSION, $request->getBaseUrl()]));
		$cacheFile = $dir . '/' . $key . '.json';
		if (is_file($cacheFile)) {
			$c = json_decode((string) @file_get_contents($cacheFile), true);
			if (is_array($c) && isset($c['html'])) return $c;
		}
		try {
			$xml = Services::get('file')->fs->read($file->getData('path'));
		} catch (\Throwable $e) {
			error_log('cheesaFullText: ' . $e->getMessage());
			return null;
		}
		$images = $this->dependentFiles($article, $file);
		$conv = new CheesaJats();
		$conv->imageResolver = function ($href) use ($images, $request, $article, $galley) {
			$name = strtolower(basename(str_replace('\\', '/', $href)));
			$stem = preg_replace('/\.[a-z0-9]+$/', '', $name);
			$id = $images[$name] ?? $images['~' . $stem] ?? null;
			return $id ? $request->url(null, 'article', 'download', [$article->getBestId(), $galley->getBestGalleyId(), $id]) : null;
		};
		$r = $conv->convert($xml);
		if (!$r) return null;
		if (!is_dir($dir)) @mkdir($dir, 0775, true);
		foreach (glob($dir . '/' . (int) $file->getId() . '-*.json') ?: [] as $old) @unlink($old);   // buang versi lama
		@file_put_contents($cacheFile, json_encode($r));
		return $r;
	}

	/** Berkas pendukung galley (gambar): nama berkas => id */
	private function dependentFiles($article, $file) {
		import('lib.pkp.classes.submission.SubmissionFile');
		$map = [];
		try {
			$files = Services::get('submissionFile')->getMany([
				'submissionIds' => [$article->getId()],
				'assocTypes' => [ASSOC_TYPE_SUBMISSION_FILE],
				'assocIds' => [$file->getId()],
				'fileStages' => [SUBMISSION_FILE_DEPENDENT],
				'includeDependentFiles' => true,
			]);
			foreach ($files as $f) {
				foreach ([$f->getLocalizedData('name'), basename((string) $f->getData('path'))] as $n) {
					$n = strtolower(trim((string) $n));
					if ($n === '') continue;
					$map[$n] = $f->getId();
					$map['~' . preg_replace('/\.[a-z0-9]+$/', '', $n)] = $f->getId();
				}
			}
		} catch (\Throwable $e) {
			error_log('cheesaFullText dependent files: ' . $e->getMessage());
		}
		return $map;
	}

	/* ---------------- tampilan ---------------- */

	private function wrap(array $r, $readUrl) {
		$toc = '';
		foreach ($r['toc'] as $t) {
			$toc .= '<li class="cft-toc-l' . (int) $t[2] . '"><a href="#' . htmlspecialchars($t[0], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($t[1], ENT_QUOTES, 'UTF-8') . '</a></li>';
		}
		$stats = [];
		if ($r['figures']) $stats[] = $r['figures'] . ' figure' . ($r['figures'] > 1 ? 's' : '');
		if ($r['tables']) $stats[] = $r['tables'] . ' table' . ($r['tables'] > 1 ? 's' : '');
		if ($r['refs']) $stats[] = $r['refs'] . ' reference' . ($r['refs'] > 1 ? 's' : '');
		$tools = '<div class="cft-tools">'
			. ($stats ? '<span class="cft-stats">' . htmlspecialchars(implode(' · ', $stats), ENT_QUOTES, 'UTF-8') . '</span>' : '')
			. '<span class="cft-tools-r"><button type="button" class="cft-size" data-cft-size="-1" aria-label="Smaller text">A−</button><button type="button" class="cft-size" data-cft-size="1" aria-label="Larger text">A+</button>'
			. ($readUrl ? '<a class="cft-open" href="' . htmlspecialchars($readUrl, ENT_QUOTES, 'UTF-8') . '">Open reading view ↗</a>' : '')
			. '</span></div>';
		return '<div class="cft"' . ($r['hasTex'] ? ' data-cft-tex="1"' : '') . '>' . $tools
			. ($toc ? '<nav class="cft-toc" aria-label="Contents"><p class="cft-toc-h">Contents</p><ol>' . $toc . '</ol></nav>' : '')
			. '<div class="cft-text">' . $r['html'] . '</div></div>';
	}

	private function addAssets($request) {
		$tm = TemplateManager::getManager($request);
		$base = $request->getBaseUrl() . '/' . $this->getPluginPath();
		$tm->addStyleSheet('cheesaFullText', $base . '/css/fulltext.css' . (preg_match('#<release>\\s*([0-9.]+)#', (string) @file_get_contents(__DIR__ . '/version.xml'), $vm) ? '?v=' . $vm[1] : ''), ['contexts' => 'frontend', 'priority' => defined('STYLE_SEQUENCE_LAST') ? STYLE_SEQUENCE_LAST : 15]);
		$tm->addJavaScript('cheesaFullText', $base . '/js/fulltext.js' . (preg_match('#<release>\\s*([0-9.]+)#', (string) @file_get_contents(__DIR__ . '/version.xml'), $vm) ? '?v=' . $vm[1] : ''), ['contexts' => 'frontend', 'priority' => defined('STYLE_SEQUENCE_LAST') ? STYLE_SEQUENCE_LAST : 15]);
	}

	private function pdfUrl($request, $article, $publication) {
		foreach ((array) $publication->getData('galleys') as $g) {
			$f = $g->getFile();
			if ($f && $f->getData('mimetype') === 'application/pdf') return $request->url(null, 'article', 'view', [$article->getBestId(), $g->getBestGalleyId()]);
		}
		return null;
	}

	/* ---------------- galley XML ---------------- */

	private function xmlGalley($publication) {
		foreach ((array) $publication->getData('galleys') as $g) if ($this->isXmlGalley($g)) return $g;
		return null;
	}

	private function isXmlGalley($galley) {
		if (!$galley || $galley->getRemoteURL()) return false;
		$f = $galley->getFile();
		if (!$f) return false;
		if (in_array((string) $f->getData('mimetype'), self::XML_TYPES, true)) return true;
		return (bool) preg_match('/\.xml$/i', (string) $f->getData('path')) || strtolower(trim((string) $galley->getLabel())) === 'xml';
	}
}
