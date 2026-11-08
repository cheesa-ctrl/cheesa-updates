<?php

/**
 * @file plugins/generic/crossmarkButton/CrossmarkButtonPlugin.inc.php
 *
 * Crossmark Button (OJS 3.3)
 * Menampilkan tombol Crossmark "Check for updates" (widget resmi Crossref v2) di halaman artikel
 * yang memiliki DOI, dan menambahkan meta dc.identifier yang dibaca widget.
 * Hanya aktifkan bila jurnal sudah terdaftar Crossmark di Crossref.
 */

import('lib.pkp.classes.plugins.GenericPlugin');
require_once(__DIR__ . '/classes/CheesaLicense.inc.php');

class CrossmarkButtonPlugin extends GenericPlugin {

	const WIDGET_JS = 'https://crossmark-cdn.crossref.org/widget/v2.0/widget.js';

	public static function defaults() {
		return ['logo' => 'horizontal', 'position' => 'title', 'minDate' => ''];
	}

	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled($mainContextId) && !CheesaLicense::checkRequest(__DIR__)) {
			HookRegistry::register('ArticleHandler::view', [$this, 'onArticleView']);
			HookRegistry::register('Templates::Article::Main', [$this, 'addButton']);
		}
		return $success;
	}

	public function getDisplayName() { return 'Crossmark Button'; }

	public function getDescription() {
		return 'Tombol Crossmark "Check for updates" (widget resmi Crossref) di halaman artikel yang memiliki DOI. Aktifkan hanya bila jurnal terdaftar Crossmark.' . CheesaLicense::notice(CheesaLicense::checkRequest(__DIR__));
	}

	public function opt($contextId, $key) {
		$v = $this->getSetting($contextId, $key);
		if ($v === null || $v === '') { $d = self::defaults(); return isset($d[$key]) ? $d[$key] : ''; }
		return $v;
	}

	/** Hook ArticleHandler::view — tambah meta dc.identifier + script widget bila artikel ber-DOI. */
	public function onArticleView($hookName, $args) {
		try {
			$request = $args[0]; $article = $args[2];
			$doi = $this->doiOf($article);
			if (!$doi || !$this->dateOk($article, $request)) return false;
			$tm = TemplateManager::getManager($request);
			$tm->addHeader('crossmarkDoi', '<meta name="dc.identifier" content="doi:' . htmlspecialchars($doi, ENT_QUOTES, 'UTF-8') . '">');
			$tm->addJavaScript('crossmarkWidget', self::WIDGET_JS, ['contexts' => 'frontend']);
			$tm->addStyleSheet('crossmarkButton', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/crossmark.css', ['contexts' => 'frontend']);
			$tm->addJavaScript('crossmarkButton', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/crossmark.js', ['contexts' => 'frontend']);
		} catch (\Throwable $e) {
			error_log('crossmarkButton: ' . $e->getMessage());
		}
		return false;
	}

	/** Hook Templates::Article::Main — sisipkan tombol (dipindah ke dekat judul oleh crossmark.js / tema). */
	public function addButton($hookName, $args) {
		$smarty = $args[1]; $output =& $args[2];
		try {
			$article = $smarty->getTemplateVars('article');
			$request = Application::get()->getRequest();
			$doi = $this->doiOf($article);
			$cid = $request->getContext()->getId();
			if (!$doi || !$this->dateOk($article, $request)) {
				// Diagnosa: komentar di sumber halaman + catatan kecil yang hanya terlihat oleh Journal Manager / Admin.
				$why = !$doi ? 'artikel ini belum memiliki DOI (isi/assign DOI di tab Identifiers lalu publish ulang)'
					: 'tanggal terbit artikel lebih awal dari batas "Hanya artikel terbit sejak" di pengaturan plugin';
				$output .= "\n<!-- Crossmark Button: tidak ditampilkan karena " . $why . " -->\n";
				$user = $request->getUser();
				if ($user && $user->hasRole([ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN], $cid)) {
					$output .= '<p class="cmk-note" style="margin:10px 0;padding:8px 12px;border-left:4px solid #ed991f;background:#fff8ec;font-size:13px">'
						. 'Crossmark tidak tampil: ' . htmlspecialchars($why, ENT_QUOTES, 'UTF-8') . '. (Catatan ini hanya terlihat oleh pengelola jurnal.)</p>';
				}
				return false;
			}
			$logo = $this->opt($cid, 'logo') === 'stacked' ? 'CROSSMARK_Color_square_no_text.svg' : 'CROSSMARK_Color_horizontal.svg';
			$pos = preg_replace('/[^a-z]/', '', $this->opt($cid, 'position'));
			$output .= '<div class="cmk-wrap" data-position="' . $pos . '">'
				. '<a class="cmk-button" data-target="crossmark" href="#" title="Check for updates via Crossmark">'
				. '<img src="https://crossmark-cdn.crossref.org/widget/v2.0/logos/' . $logo . '" alt="Check for updates" width="150">'
				. '</a></div>';
		} catch (\Throwable $e) {
			error_log('crossmarkButton: ' . $e->getMessage());
		}
		return false;
	}

	private function doiOf($article) {
		if (!$article || !method_exists($article, 'getCurrentPublication')) return '';
		$pub = $article->getCurrentPublication();
		return $pub ? trim((string) $pub->getStoredPubId('doi')) : '';
	}

	/** Opsional: hanya artikel yang terbit pada/sesudah tanggal tertentu (mis. sejak terdaftar Crossmark). */
	private function dateOk($article, $request) {
		$min = $this->opt($request->getContext()->getId(), 'minDate');
		if (!$min) return true;
		$pub = $article->getCurrentPublication();
		$d = $pub ? $pub->getData('datePublished') : null;
		return !$d || strtotime($d) >= strtotime($min);
	}

	/* ---------------- Pengaturan ---------------- */
	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) return $actions;
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$router = $request->getRouter();
		array_unshift($actions, new LinkAction('settings',
			new AjaxModal($router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']), $this->getDisplayName()),
			__('manager.plugins.settings'), null));
		return $actions;
	}

	public function manage($args, $request) {
		if ($request->getUserVar('verb') === 'settings') {
			$cid = $request->getContext()->getId();
			if ($request->getUserVar('save')) {
				foreach (array_keys(self::defaults()) as $k) $this->updateSetting($cid, $k, trim((string) $request->getUserVar($k)), 'string');
				return new JSONMessage(true);
			}
			$tm = TemplateManager::getManager($request);
			$vals = [];
			foreach (array_keys(self::defaults()) as $k) $vals[$k] = $this->opt($cid, $k);
			$tm->assign(['pluginName' => $this->getName(), 'cmk' => $vals]);
			return new JSONMessage(true, $tm->fetch($this->getTemplateResource('settings.tpl')));
		}
		return parent::manage($args, $request);
	}
}
