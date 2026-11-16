<?php

/**
 * @file plugins/generic/cheesaControlPanel/CheesaPanelHandler.inc.php
 *
 * Halaman penuh CHEESA Control Panel: /index.php/<jurnal>/cheesapanel
 * Hanya kerangka halaman; isi panel dimuat dari aksi "manage" plugin (sama seperti modal),
 * sehingga semua pemeriksaan hak akses, CSRF, dan logika update/hapus tetap di satu tempat.
 */

import('classes.handler.Handler');

class CheesaPanelHandler extends Handler {

	/** @var CheesaControlPanelPlugin */
	public static $plugin;

	public function __construct() {
		parent::__construct();
		$this->addRoleAssignment([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], ['index']);
	}

	public function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	public function index($args, $request) {
		$plugin = self::$plugin;
		$context = $request->getContext();
		$dispatcher = $request->getDispatcher();
		$tm = TemplateManager::getManager($request);
		$tm->assign([
			'cpJournal' => $context ? $context->getLocalizedName() : '',
			'cpManageUrl' => $plugin->manageUrl($request),
			'cpDashUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'submissions'),
			'cpPluginsUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'management', 'settings', 'website', null, 'plugins'),
			'cpSiteUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'index'),
			'cpUserName' => $request->getUser() ? $request->getUser()->getFullName() : '',
		]);
		$tm->display($plugin->getTemplateResource('page.tpl'));
	}
}
