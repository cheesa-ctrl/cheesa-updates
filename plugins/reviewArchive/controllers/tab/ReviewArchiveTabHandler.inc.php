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
		$templateMgr->assign([
			'submission' => $submission,
			'markedFiles' => $marked,
			'hiddenFiles' => $hidden,
			'reviewMarker' => self::MARKER,
		]);
		return $templateMgr->fetchJson(self::$plugin->getTemplateResource('reviewArchiveTab.tpl'));
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
}
