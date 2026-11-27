<?php

/**
 * @file plugins/generic/reviewArchive/controllers/tab/ReviewArchiveTabHandler.inc.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewArchiveTabHandler
 * @ingroup plugins_generic_reviewArchive
 *
 * @brief Renders the "Arsip Review" tab in the editorial workflow.
 */

import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');
import('lib.pkp.classes.submission.SubmissionFile');

class ReviewArchiveTabHandler extends Handler {

	/** Marker put in front of a file name to show it in the archive. */
	const MARKER = '[REVIEW]';

	/** @var ReviewArchivePlugin */
	static $plugin;

	/**
	 * @param $plugin ReviewArchivePlugin
	 */
	static function setPlugin($plugin) {
		self::$plugin = $plugin;
	}

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			[ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
			['fetchTab']
		);
		$this->addRoleAssignment(
			[ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
			['saveStatement', 'saveDriveFolder', 'saveDiscussionHtml']
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', WORKFLOW_STAGE_ID_SUBMISSION, WORKFLOW_TYPE_EDITORIAL));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Render the tab.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage
	 */
	function fetchTab($args, $request) {
		$this->setupTemplate($request);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION); /** @var $submission Submission */

		// File stages that the normal workflow grids only show inside a review
		// round, review assignment or discussion. After a Native XML import
		// those do not exist, so these files would otherwise be invisible.
		$hiddenStages = [
			SUBMISSION_FILE_REVIEW_FILE,
			SUBMISSION_FILE_REVIEW_ATTACHMENT,
			SUBMISSION_FILE_REVIEW_REVISION,
			SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
			SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
			SUBMISSION_FILE_ATTACHMENT,
			SUBMISSION_FILE_QUERY,
			SUBMISSION_FILE_NOTE,
		];

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /** @var $submissionFileDao SubmissionFileDAO */
		$dispatcher = $request->getDispatcher();

		$marked = [];
		$hidden = [];
		$submissionFiles = Services::get('submissionFile')->getMany([
			'submissionIds' => [$submission->getId()],
		]);
		foreach ($submissionFiles as $submissionFile) { /** @var $submissionFile SubmissionFile */
			$name = (string) $submissionFile->getLocalizedData('name');
			$fileStage = (int) $submissionFile->getData('fileStage');
			$markerPos = mb_stripos($name, self::MARKER);
			$isMarked = $markerPos !== false;
			if (!$isMarked && !in_array($fileStage, $hiddenStages)) continue;

			// "cheesa, Journal manager, [REVIEW] review 1.doc" -> "review 1.doc"
			$displayName = $isMarked
				? trim(mb_substr($name, $markerPos + mb_strlen(self::MARKER)), " -\t")
				: $name;

			$stageId = $this->_workflowStageForFileStage($fileStage);
			$revisions = [];
			$revisionRows = $submissionFileDao->getRevisions($submissionFile->getId());
			$total = count($revisionRows);
			$i = 0;
			foreach ($revisionRows as $revision) {
				$revisions[] = [
					'number' => $total - $i,
					'latest' => $i === 0,
					'url' => $dispatcher->url($request, ROUTE_COMPONENT, null, 'api.file.FileApiHandler', 'downloadFile', null, [
						'submissionFileId' => $submissionFile->getId(),
						'submissionId' => $submission->getId(),
						'stageId' => $stageId,
						'fileId' => $revision->fileId,
					]),
				];
				$i++;
			}

			$row = [
				'id' => $submissionFile->getId(),
				'name' => $displayName !== '' ? $displayName : $name,
				'stage' => __($this->_fileStageLabelKey($fileStage)),
				'date' => $submissionFile->getData('createdAt'),
				'revisions' => $revisions,
			];
			if ($isMarked) {
				$marked[] = $row;
			} else {
				$hidden[] = $row;
			}
		}

		$sortByDate = function($a, $b) { return strcmp((string) $a['date'], (string) $b['date']); };
		usort($marked, $sortByDate);
		usort($hidden, $sortByDate);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign($this->_statementVars($request, $submission));
		$templateMgr->assign($this->_discussionVars($request, $submission));
		$templateMgr->assign($this->_driveVars($request, $submission));
		$templateMgr->assign([
			'submission' => $submission,
			'markedFiles' => $marked,
			'hiddenFiles' => $hidden,
			'reviewMarker' => self::MARKER,
		]);
		return $templateMgr->fetchJson(self::$plugin->getTemplateResource('reviewArchiveTab.tpl'));
	}

	/**
	 * Save the per-article editor statement and return the refreshed box.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage
	 */
	function saveStatement($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) {
			return new JSONMessage(false, __('form.csrfInvalid'));
		}
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION); /** @var $submission Submission */
		$user = $request->getUser();
		$text = trim((string) $request->getUserVar('reviewArchiveStatement'));
		if (mb_strlen($text) > 20000) $text = mb_substr($text, 0, 20000);

		$submission->setData('reviewArchiveStatement', $text);
		$submission->setData('reviewArchiveStatementBy', $user ? $user->getFullName() : '');
		$submission->setData('reviewArchiveStatementAt', Core::getCurrentDate());
		DAORegistry::getDAO('SubmissionDAO')->updateObject($submission);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign($this->_statementVars($request, $submission));
		$templateMgr->assign('submission', $submission);
		return new JSONMessage(true, $templateMgr->fetch(self::$plugin->getTemplateResource('statementBox.tpl')));
	}

	/**
	 * Template variables for the "Pernyataan Editor" box.
	 * @param $request PKPRequest
	 * @param $submission Submission
	 * @return array
	 */
	protected function _statementVars($request, $submission) {
		$plugin = self::$plugin;
		$context = $request->getContext();
		$userRoles = (array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

		// The journal-wide text explains a migrated review history, so it is
		// only shown for articles that have no review round in this OJS.
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var $reviewRoundDao ReviewRoundDAO */
		$hasReviewRounds = count($reviewRoundDao->getBySubmissionId($submission->getId())->toArray()) > 0;
		$default = $hasReviewRounds ? '' : $plugin->fillPlaceholders($plugin->getDefaultStatement($context->getId()), $submission, $context);
		$note = (string) $submission->getData('reviewArchiveStatement');
		$sourceId = $plugin->getSourceId($submission);

		return [
			'statementDefaultHtml' => $default !== '' ? $plugin->formatText($default) : '',
			'statementNote' => $note,
			'statementNoteHtml' => $note !== '' ? $plugin->formatText($plugin->fillPlaceholders($note, $submission, $context)) : '',
			'statementBy' => (string) $submission->getData('reviewArchiveStatementBy'),
			'statementAt' => (string) $submission->getData('reviewArchiveStatementAt'),
			'statementCanEdit' => (bool) array_intersect([ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_SITE_ADMIN], $userRoles),
			'statementSaveUrl' => $request->getDispatcher()->url($request, ROUTE_COMPONENT, null, ReviewArchivePlugin::TAB_COMPONENT, 'saveStatement', null, ['submissionId' => $submission->getId()]),
			'statementSourceId' => $sourceId,
			'certificates' => $plugin->getCertificates($context->getId(), $sourceId),
			'csrfToken' => $request->getSession()->getCSRFToken(),
			'statementTemplate' => $plugin->getTemplateResource('statementBox.tpl'),
		];
	}

	/**
	 * The workflow stage to send with a download link, so the file access
	 * policy checks the stage the file belongs to.
	 * @param $fileStage int
	 * @return int
	 */
	protected function _workflowStageForFileStage($fileStage) {
		switch ($fileStage) {
			case SUBMISSION_FILE_INTERNAL_REVIEW_FILE:
			case SUBMISSION_FILE_INTERNAL_REVIEW_REVISION:
				return WORKFLOW_STAGE_ID_INTERNAL_REVIEW;
			case SUBMISSION_FILE_REVIEW_FILE:
			case SUBMISSION_FILE_REVIEW_ATTACHMENT:
			case SUBMISSION_FILE_REVIEW_REVISION:
			case SUBMISSION_FILE_ATTACHMENT:
				return WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;
			case SUBMISSION_FILE_FINAL:
			case SUBMISSION_FILE_COPYEDIT:
				return WORKFLOW_STAGE_ID_EDITING;
			case SUBMISSION_FILE_PROOF:
			case SUBMISSION_FILE_PRODUCTION_READY:
				return WORKFLOW_STAGE_ID_PRODUCTION;
			default:
				return WORKFLOW_STAGE_ID_SUBMISSION;
		}
	}

	/**
	 * @param $fileStage int
	 * @return string locale key
	 */
	protected function _fileStageLabelKey($fileStage) {
		$keys = [
			SUBMISSION_FILE_SUBMISSION => 'submission',
			SUBMISSION_FILE_REVIEW_FILE => 'reviewFile',
			SUBMISSION_FILE_REVIEW_ATTACHMENT => 'reviewAttachment',
			SUBMISSION_FILE_REVIEW_REVISION => 'reviewRevision',
			SUBMISSION_FILE_INTERNAL_REVIEW_FILE => 'reviewFile',
			SUBMISSION_FILE_INTERNAL_REVIEW_REVISION => 'reviewRevision',
			SUBMISSION_FILE_ATTACHMENT => 'attachment',
			SUBMISSION_FILE_QUERY => 'query',
			SUBMISSION_FILE_NOTE => 'note',
			SUBMISSION_FILE_FINAL => 'final',
			SUBMISSION_FILE_COPYEDIT => 'copyedit',
			SUBMISSION_FILE_PROOF => 'proof',
			SUBMISSION_FILE_PRODUCTION_READY => 'productionReady',
		];
		return 'plugins.generic.reviewArchive.fileStage.' . ($keys[$fileStage] ?? 'other');
	}

	//
	// Feature: review discussions (OJS Query threads) for this article
	//
	/**
	 * Collect the submission's discussion threads (editor/author/reviewer
	 * conversations) with their messages and attachments, so a migrated or
	 * accreditation review keeps its correspondence visible. Fails open.
	 * @param $request PKPRequest
	 * @param $submission Submission
	 * @return array ['discussions' => [...]]
	 */
	protected function _discussionVars($request, $submission) {
		$out = [
			'discussions' => [],
			'discussionCanEdit' => (bool) array_intersect(
				[ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_SITE_ADMIN],
				(array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES)
			),
			'discussionText' => (string) $submission->getData('reviewArchiveDiscussionText'),
			'discussionSaveUrl' => $request->getDispatcher()->url(
				$request, ROUTE_COMPONENT, null, ReviewArchivePlugin::TAB_COMPONENT,
				'saveDiscussionHtml', null, ['submissionId' => $submission->getId()]
			),
		];
		try {
			$queryDao = DAORegistry::getDAO('QueryDAO'); /** @var $queryDao QueryDAO */
			$seen = [];
			$list = [];

			// Live discussions straight from the OJS database.
			$queries = $queryDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submission->getId());
			while ($query = $queries->next()) { /** @var $query Query */
				$list[] = $this->_threadFromQuery($request, $submission, $query);
				$seen[(int) $query->getId()] = true;
			}

			// Pasted archive: a full panel, or a list whose rows carry a
			// queryId we can resolve to the real thread in the database.
			$pasted = (string) $submission->getData('reviewArchiveDiscussionText');
			if (trim($pasted) !== '') {
				self::$plugin->import('classes.DiscussionParser');
				foreach (DiscussionParser::parse($pasted) as $d) {
					if (!empty($d['indexOnly'])) {
						$qid = isset($d['queryId']) ? (int) $d['queryId'] : 0;
						if ($qid && isset($seen[$qid])) continue; // already shown live
						$q = $qid ? $queryDao->getById($qid, ASSOC_TYPE_SUBMISSION, $submission->getId()) : null;
						if ($q) { // pull the real content from the DB
							$list[] = $this->_threadFromQuery($request, $submission, $q);
							$seen[$qid] = true;
							continue;
						}
						// Not in this database: keep it as an index entry + link.
						$list[] = [
							'title' => $d['title'], 'stage' => 'daftar',
							'participants' => [], 'messages' => [], 'indexOnly' => true,
							'link' => (string) ($d['link'] ?? ''), 'from' => (string) ($d['from'] ?? ''),
							'date' => (string) ($d['date'] ?? ''), 'replies' => (string) ($d['replies'] ?? ''),
							'closed' => !empty($d['closed']),
						];
						continue;
					}
					// Full panel paste.
					$msgs = [];
					foreach ($d['messages'] as $m) {
						$msgs[] = [
							'from' => $m['from'], 'date' => $m['date'], 'title' => $m['title'],
							'bodyHtml' => $m['body'] !== '' ? self::$plugin->formatText($m['body']) : '',
							'attachments' => $m['attachments'],
						];
					}
					$stage = (!empty($d['stage'])) ? $d['stage'] : 'arsip tempel';
					$list[] = [
						'title' => $d['title'], 'stage' => $stage,
						'participants' => $d['participants'], 'messages' => $msgs,
					];
				}
			}

			$out['discussions'] = $list;
		} catch (\Throwable $e) {
			$out['discussions'] = [];
			$out['discussionsError'] = $e->getMessage();
		}
		return $out;
	}

	/**
	 * Build one discussion thread (title, stage, participants, messages with
	 * attachments) from an OJS Query object.
	 * @param $request PKPRequest
	 * @param $submission Submission
	 * @param $query Query
	 * @return array
	 */
	protected function _threadFromQuery($request, $submission, $query) {
		$dispatcher = $request->getDispatcher();
		$noteDao = DAORegistry::getDAO('NoteDAO'); /** @var $noteDao NoteDAO */
		$userDao = DAORegistry::getDAO('UserDAO'); /** @var $userDao UserDAO */
		$queryDao = DAORegistry::getDAO('QueryDAO'); /** @var $queryDao QueryDAO */
		$fileService = Services::get('submissionFile');

		$headNote = $query->getHeadNote();
		$title = $headNote ? trim((string) $headNote->getTitle()) : '';

		$participants = [];
		foreach ((array) $queryDao->getParticipantIds($query->getId()) as $uid) {
			$u = $userDao->getById($uid);
			if ($u) $participants[] = $u->getFullName();
		}

		$messages = [];
		$notes = $noteDao->getByAssoc(ASSOC_TYPE_QUERY, $query->getId());
		while ($note = $notes->next()) { /** @var $note Note */
			$u = $userDao->getById($note->getUserId());
			$atts = [];
			$noteFiles = $fileService->getMany([
				'submissionIds' => [$submission->getId()],
				'assocTypes' => [ASSOC_TYPE_NOTE],
				'assocIds' => [$note->getId()],
			]);
			foreach ($noteFiles as $sf) { /** @var $sf SubmissionFile */
				$atts[] = [
					'name' => (string) $sf->getLocalizedData('name'),
					'url' => $dispatcher->url($request, ROUTE_COMPONENT, null, 'api.file.FileApiHandler', 'downloadFile', null, [
						'submissionFileId' => $sf->getId(),
						'submissionId' => $submission->getId(),
						'stageId' => $query->getStageId(),
						'fileId' => $sf->getData('fileId'),
					]),
				];
			}
			$raw = (string) $note->getContents();
			$raw = preg_replace('#<\s*(br|/p|/div|/li)\b[^>]*>#i', "\n", $raw);
			$body = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			$messages[] = [
				'from' => $u ? $u->getFullName() : '',
				'date' => (string) $note->getDateCreated(),
				'title' => trim((string) $note->getTitle()),
				'bodyHtml' => $body !== '' ? self::$plugin->formatText($body) : '',
				'attachments' => $atts,
			];
		}

		return [
			'title' => $title !== '' ? $title : ('Diskusi #' . $query->getId()),
			'stage' => $this->_stageName($query->getStageId()),
			'participants' => $participants,
			'messages' => $messages,
		];
	}

	/**
	 * Save (or clear) the pasted discussion-HTML archive for this article.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage
	 */
	function saveDiscussionHtml($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) {
			return new JSONMessage(false, __('form.csrfInvalid'));
		}
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION); /** @var $submission Submission */
		$raw = (string) $request->getUserVar('reviewArchiveDiscussionText');
		// Scripts are dropped (noise/risk); cap the stored size.
		$raw = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $raw);
		$raw = trim($raw);
		if (mb_strlen($raw) > 300000) $raw = mb_substr($raw, 0, 300000);
		$submission->setData('reviewArchiveDiscussionText', $raw);
		DAORegistry::getDAO('SubmissionDAO')->updateObject($submission);
		return new JSONMessage(true, '');
	}

	/**
	 * Short stage label for a discussion.
	 * @param $stageId int
	 * @return string
	 */
	protected function _stageName($stageId) {
		switch ((int) $stageId) {
			case WORKFLOW_STAGE_ID_SUBMISSION: return 'Pre Review';
			case WORKFLOW_STAGE_ID_INTERNAL_REVIEW: return 'Internal Review';
			case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW: return 'Review';
			case WORKFLOW_STAGE_ID_EDITING: return 'Copyediting';
			case WORKFLOW_STAGE_ID_PRODUCTION: return 'Production';
			default: return '';
		}
	}

	//
	// Feature: Google Drive review files for this one article
	//
	/**
	 * Save a manual per-article override link (an exact Drive sub-folder),
	 * used when auto-matching picks the wrong folder or none.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage
	 */
	function saveDriveFolder($args, $request) {
		if (!$request->isPost() || !$request->checkCSRF()) {
			return new JSONMessage(false, __('form.csrfInvalid'));
		}
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION); /** @var $submission Submission */
		// Store the raw link (empty clears it, back to auto-matching).
		$raw = trim((string) $request->getUserVar('reviewArchiveDriveFolder'));
		$submission->setData('reviewArchiveDriveFolder', mb_substr($raw, 0, 500));
		DAORegistry::getDAO('SubmissionDAO')->updateObject($submission);
		return new JSONMessage(true, '');
	}

	/**
	 * Build the "Berkas Review (Google Drive)" section for this article.
	 * Everything fails open: a missing key, missing collection folder, no
	 * match or any network error just leaves the section empty/with a note.
	 * @param $request PKPRequest
	 * @param $submission Submission
	 * @return array template vars
	 */
	protected function _driveVars($request, $submission) {
		$plugin = self::$plugin;
		$context = $request->getContext();
		$off = [
			'driveEnabled' => false, 'driveMatched' => false, 'driveGroups' => [],
			'driveFolderName' => '', 'driveFolderUrl' => '', 'driveReason' => '',
			'driveMessage' => '', 'driveCount' => 0, 'driveReviewers' => [],
			'driveOverrideUrl' => (string) $submission->getData('reviewArchiveDriveFolder'),
			'driveCanEdit' => (bool) array_intersect(
				[ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_SITE_ADMIN],
				(array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES)
			),
			'driveSaveUrl' => $request->getDispatcher()->url(
				$request, ROUTE_COMPONENT, null, ReviewArchivePlugin::TAB_COMPONENT,
				'saveDriveFolder', null, ['submissionId' => $submission->getId()]
			),
		];
		if (!$context) return $off;

		$plugin->import('classes.DriveClient');
		$key = trim((string) $plugin->getSetting($context->getId(), 'gdriveApiKey'));
		$override = trim((string) $submission->getData('reviewArchiveDriveFolder'));
		$collection = trim((string) $plugin->getSetting($context->getId(), 'gdriveCollectionFolder'));

		// Nothing to show unless a key plus either a collection folder or an
		// explicit per-article link is available.
		if ($key === '' || ($collection === '' && $override === '')) return $off;
		$off['driveEnabled'] = true;

		try {
			$targetId = null;
			if ($override !== '') {
				$targetId = DriveClient::folderId($override);
				if ($targetId) { $off['driveReason'] = 'tautan manual'; $off['driveMatched'] = true; }
			}
			if (!$targetId && $collection !== '') {
				$collId = DriveClient::folderId($collection);
				if (!$collId) { $off['driveMessage'] = 'Link folder kumpulan di pengaturan tidak dikenali.'; return $off; }
				$subs = $this->_cachedSubfolders($context->getId(), $collId, $key);
				$match = DriveClient::matchFolder($subs, [
					'ids' => $this->_matchIds($submission),
					'surnames' => $this->_authorSurnames($submission),
				]);
				if ($match) {
					$targetId = $match['id'];
					$off['driveMatched'] = true;
					$off['driveFolderName'] = $match['name'];
					$off['driveReason'] = $match['reason'];
				} else {
					$off['driveMessage'] = 'Belum ada subfolder yang cocok dengan artikel ini di folder kumpulan. '
						. 'Editor bisa menempel tautan subfolder artikel secara manual di bawah.';
					return $off;
				}
			}
			if (!$targetId) { $off['driveMessage'] = 'Tautan manual tidak dikenali.'; return $off; }

			$off['driveFolderUrl'] = DriveClient::folderUrl($targetId);
			$groups = DriveClient::tree($targetId, $key);
			list($rendered, $count, $reviewers) = $this->_renderDriveGroups($groups);
			$off['driveGroups'] = $rendered;
			$off['driveCount'] = $count;
			$off['driveReviewers'] = $reviewers;
			if ($count === 0) $off['driveMessage'] = 'Folder ditemukan tetapi kosong atau belum dibagikan "Anyone with the link".';
		} catch (\Throwable $e) {
			$off['driveMatched'] = false;
			$off['driveGroups'] = [];
			$off['driveMessage'] = 'Gagal membaca Google Drive: ' . $e->getMessage()
				. ' (pastikan folder di-share "Anyone with the link" dan Drive API aktif untuk kunci).';
		}
		return $off;
	}

	/**
	 * Cached (15 min) list of the collection folder's sub-folders, so opening
	 * article tabs does not hit the Drive API on every load.
	 * @param $contextId int
	 * @param $collId string
	 * @param $key string
	 * @return array [['id','name'], ...]
	 * @throws Exception
	 */
	protected function _cachedSubfolders($contextId, $collId, $key) {
		$plugin = self::$plugin;
		$raw = (string) $plugin->getSetting($contextId, 'gdriveCollCache');
		$cache = $raw !== '' ? json_decode($raw, true) : null;
		if (is_array($cache) && ($cache['fid'] ?? '') === $collId
				&& (time() - (int) ($cache['at'] ?? 0)) < 900 && isset($cache['subs'])) {
			return $cache['subs'];
		}
		$subs = DriveClient::subfolders($collId, $key);
		$plugin->updateSetting($contextId, 'gdriveCollCache', json_encode([
			'fid' => $collId, 'at' => time(), 'subs' => $subs,
		]), 'string');
		return $subs;
	}

	/**
	 * Match signals from the DOI number and the current submission id.
	 * @param $submission Submission
	 * @return array numeric strings
	 */
	protected function _matchIds($submission) {
		$ids = [(string) $submission->getId()];
		$sourceId = self::$plugin->getSourceId($submission); // DOI number, else submission id
		if ($sourceId !== '') $ids[] = (string) $sourceId;
		return array_values(array_unique($ids));
	}

	/**
	 * Family names of the article's authors, for name-based folder matching.
	 * @param $submission Submission
	 * @return array lower-case family names
	 */
	protected function _authorSurnames($submission) {
		$names = [];
		$publication = $submission->getCurrentPublication();
		if (!$publication) return $names;
		$authors = (array) $publication->getData('authors');
		foreach ($authors as $author) {
			if (!is_object($author)) continue;
			$fam = '';
			if (method_exists($author, 'getLocalizedFamilyName')) $fam = (string) $author->getLocalizedFamilyName();
			if ($fam === '' && method_exists($author, 'getFamilyName')) $fam = (string) $author->getFamilyName(null);
			if ($fam === '' && method_exists($author, 'getLocalizedGivenName')) $fam = (string) $author->getLocalizedGivenName();
			if ($fam !== '') $names[] = $fam;
		}
		return $names;
	}

	/**
	 * Turn raw Drive groups into template-ready rows, flag reviewer /
	 * activity-log groups, and collect reviewer labels. Activity-log groups
	 * are pushed to the end.
	 * @param $groups array from DriveClient::tree()
	 * @return array [rows, totalFiles, reviewerLabels]
	 */
	protected function _renderDriveGroups($groups) {
		$rows = [];
		$logs = [];
		$total = 0;
		$reviewers = [];
		foreach ($groups as $g) {
			$isLog = DriveClient::looksLikeActivityLog($g['name']);
			$isReviewer = (bool) preg_match('/reviewer|penelaah|mitra\s*bestari|review\s*\d/iu', (string) $g['name']);
			// The path's last segment, e.g. "2. Review File / 1. Reviewer" -> "1. Reviewer".
			$segments = preg_split('#\s*/\s*#', (string) $g['name']);
			$last = trim((string) end($segments));
			// Only collect a reviewer chip when the segment carries an actual
			// name, not just numbering + the word "Reviewer"/"Editor".
			if ($isReviewer) {
				$nameOnly = trim(preg_replace('/^\s*\d+\s*[.\)-]*\s*|reviewer|penelaah|mitra\s*bestari|review\s*file|editor/iu', '', $last));
				if (preg_match('/\p{L}{3,}/u', $nameOnly)) $reviewers[] = $last;
			}
			$files = [];
			foreach ($g['files'] as $f) {
				$id = (string) ($f['id'] ?? '');
				$mime = (string) ($f['mimeType'] ?? '');
				$isGoogle = strpos($mime, 'application/vnd.google-apps.') === 0;
				$name = (string) ($f['name'] ?? '');
				if (DriveClient::looksLikeActivityLog($name) && !$isLog) {
					// a stray log file inside a normal group: leave it in place but tagged
				}
				$files[] = [
					'id' => $id,
					'name' => $name,
					'size' => DriveClient::humanSize($f['size'] ?? 0),
					'isGoogle' => $isGoogle,
					'canPreview' => !$isGoogle && $id !== '',
					'openUrl' => 'https://drive.google.com/open?id=' . rawurlencode($id),
					'dlUrl' => 'https://drive.google.com/uc?export=download&id=' . rawurlencode($id),
					'isCert' => DriveClient::looksLikeCertificate($name),
					'isLog' => DriveClient::looksLikeActivityLog($name),
				];
				$total++;
			}
			$row = ['name' => (string) $g['name'], 'isLog' => $isLog, 'isReviewer' => $isReviewer, 'files' => $files];
			if ($isLog) $logs[] = $row; else $rows[] = $row;
		}
		foreach ($logs as $row) $rows[] = $row; // logs last
		$reviewers = array_values(array_unique($reviewers));
		return [$rows, $total, $reviewers];
	}
}
