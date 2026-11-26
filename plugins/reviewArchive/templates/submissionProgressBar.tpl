{**
 * plugins/generic/reviewArchive/templates/submissionProgressBar.tpl
 *
 * Distributed under the GNU GPL v3.
 *
 * Copy of lib/pkp/templates/workflow/submissionProgressBar.tpl (OJS 3.3)
 * with one extra tab: "Arsip Review".
 *}
{* Calculate the selected tab index for the current stage *}
{assign var=selectedTabIndex value=0}
{foreach from=$workflowStages item=$stage}
	{if $stage.id < $currentStageId}
		{assign var=selectedTabIndex value=$selectedTabIndex+1}
	{/if}
{/foreach}

<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#stageTabs').pkpHandler(
			'$.pkp.controllers.tab.workflow.WorkflowTabHandler',
			{ldelim}
				selected: {$selectedTabIndex},
				emptyLastTab: true
			{rdelim}
		);
	{rdelim});
</script>
<div id="stageTabs" class="pkp_controllers_tab">
	<ul>
		{foreach from=$workflowStages item=$stage}
			<li class="pkp_workflow_{$stage.path} stageId{$stage.id}{if $stage.id === $currentStageId} initiated{/if}">
				<a href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.workflow.WorkflowTabHandler" op="fetchTab" submissionId=$submission->getId() stageId=$stage.id escape=false}">
					{translate key=$stage.translationKey}
				</a>
			</li>
		{/foreach}
		<li class="pkp_workflow_reviewArchive">
			<a class="reviewArchive" href="{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.reviewArchive.controllers.tab.ReviewArchiveTabHandler" op="fetchTab" submissionId=$submission->getId() escape=false}">
				{translate key="plugins.generic.reviewArchive.tabTitle"}
			</a>
		</li>
	</ul>
</div>
