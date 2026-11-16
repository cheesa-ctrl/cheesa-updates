{**
 * plugins/generic/articleStatisticsDisplay/templates/frontend/objects/galley_link.tpl
 *
 * Copyright (c) 2026 Kerim Sarıgül
 * Distributed under the GNU GPL v3.
 *
 * Enhanced galley link template with download statistics
 * This template can be copied to your theme's templates folder to override
 *}

{* Get galley views/downloads count *}
{assign var="galleyViews" value=$galley->getViews()}

{* Check if we're on article page for parent article views *}
{if $page == 'article' && isset($parent)}
    {assign var="articleViews" value=$parent->getViews()}
{/if}

{* Original galley link structure with statistics *}
<a class="obj_galley_link {$galley->getLabel()|escape|regex_replace:"/[\s]+/":"_"}" href="{url page="article" op="view" path=$article->getBestId()|to_array:$galley->getBestGalleyId()}">
    {$galley->getGalleyLabel()|escape}
    {* Statistics badge *}
    {if $galleyViews > 0}
        <span class="galley-stats">
            <sup class="asd-badge">
                <i class="fa fa-download" aria-hidden="true"></i>
                {$galleyViews|escape}
            </sup>
        </span>
    {/if}
</a>
