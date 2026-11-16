{**
 * plugins/generic/articleStatisticsDisplay/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 Kerim Sarıgül
 * Distributed under the GNU GPL v3.
 *
 * Article Statistics Display plugin settings form
 *}
<script>
    $(function() {ldelim}
        $('#articleStatisticsDisplaySettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<form
    class="pkp_form"
    id="articleStatisticsDisplaySettingsForm"
    method="POST"
    action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
    {csrf}

    {* ========== GENERAL SETTINGS ========== *}
    {fbvFormArea id="generalSettingsArea" title="plugins.generic.articleStatisticsDisplay.settings.generalSettings"}
        
        {* Display Locations *}
        {fbvFormSection title="plugins.generic.articleStatisticsDisplay.settings.displayLocations" list=true description="plugins.generic.articleStatisticsDisplay.settings.displayLocationsDescription"}
            {fbvElement
                type="checkbox"
                id="showInArticleDetail"
                label="plugins.generic.articleStatisticsDisplay.settings.showInArticleDetail"
                checked=$showInArticleDetail
            }
            {fbvElement
                type="checkbox"
                id="showInIssueToc"
                label="plugins.generic.articleStatisticsDisplay.settings.showInIssueToc"
                checked=$showInIssueToc
            }
            {fbvElement
                type="checkbox"
                id="showInHomepage"
                label="plugins.generic.articleStatisticsDisplay.settings.showInHomepage"
                checked=$showInHomepage
            }
        {/fbvFormSection}

        {* Statistics Types *}
        {fbvFormSection title="plugins.generic.articleStatisticsDisplay.settings.statisticsTypes" list=true description="plugins.generic.articleStatisticsDisplay.settings.statisticsTypesDescription"}
            {fbvElement
                type="checkbox"
                id="showAbstractViews"
                label="plugins.generic.articleStatisticsDisplay.settings.showAbstractViews"
                checked=$showAbstractViews
            }
            {fbvElement
                type="checkbox"
                id="showGalleyDownloads"
                label="plugins.generic.articleStatisticsDisplay.settings.showGalleyDownloads"
                checked=$showGalleyDownloads
            }
        {/fbvFormSection}

    {/fbvFormArea}

    {* ========== ARTICLE DETAIL PAGE SETTINGS ========== *}
    {fbvFormArea id="articleDetailSettingsArea" title="plugins.generic.articleStatisticsDisplay.settings.articleDetailSettings"}

        {* Display Style *}
        {fbvFormSection title="plugins.generic.articleStatisticsDisplay.settings.displayStyle" description="plugins.generic.articleStatisticsDisplay.settings.displayStyleDescription"}
            {fbvElement
                type="select"
                id="displayStyle"
                from=$displayStyles
                selected=$displayStyle
                translate=false
            }
        {/fbvFormSection}

        {* Position *}
        {fbvFormSection title="plugins.generic.articleStatisticsDisplay.settings.articlePosition" description="plugins.generic.articleStatisticsDisplay.settings.articlePositionDescription"}
            {fbvElement
                type="select"
                id="articleDetailPosition"
                from=$articlePositions
                selected=$articleDetailPosition
                translate=false
            }
        {/fbvFormSection}

    {/fbvFormArea}

    {fbvFormButtons submitText="common.save"}
</form>
