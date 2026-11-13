{**
 * plugins/generic/journalHero/templates/hero.tpl
 *
 * Journal Hero — rendered below the site header.
 *}
<div class="jh-root jh-{$jh.layout} jh-{$jh.size} jh-style-{$jh.style}"{if $jh.layout == 'boxed' && $jh.boxWidth} style="--jh-boxw:{$jh.boxWidth}px" data-jh-fixed{/if}>
	{if $jh.style == 'profile'}
		{assign var="jhP" value=$jh.profile}
		<section class="jh-pro{if $jh.background} jh-pro--image{/if}"{if $jh.background} style="--jh-bg:url('{$jh.background|escape}')"{/if}>
			<div class="jh-wrap jh-pro__grid{if !$jhP.cover} jh-pro__grid--nocover{/if}">

				{if $jhP.cover}
					<a class="jh-pro__cover" href="{$jhP.cover.url|escape}">
						{if $jhP.cover.image}
							<img src="{$jhP.cover.image|escape}" alt="{$jhP.cover.alt|default:$jhP.cover.label|escape}">
						{else}
							<span class="jh-pro__gencover">
								<span class="jh-pro__gencover-year">{$jhP.cover.year|escape}</span>
								<span class="jh-pro__gencover-issue">{$jhP.cover.label|escape}</span>
								<span class="jh-pro__gencover-title">{$jh.title|escape}</span>
								{if $jhP.subtitle}<span class="jh-pro__gencover-sub">{$jhP.subtitle|escape}</span>{/if}
							</span>
						{/if}
					</a>
				{/if}

				<div class="jh-pro__main">
					{if $jh.eyebrow}<div class="jh-eyebrow">{$jh.eyebrow|escape}</div>{/if}
					<h2 class="jh-pro__title">{$jh.title|escape}</h2>
					{if $jhP.subtitle}<p class="jh-pro__subtitle">{$jhP.subtitle|escape}</p>{/if}
					{if $jh.description}<p class="jh-pro__desc">{$jh.description|escape}</p>{/if}
					{if $jhP.stats}
						<dl class="jh-pro__stats">
							{foreach from=$jhP.stats item=jhStat}
								<div><dt>{$jhStat.value|escape}</dt><dd>{$jhStat.label|escape}</dd></div>
							{/foreach}
						</dl>
					{/if}
				</div>

				<div class="jh-pro__side">
					{if $jhP.tiles}
						<ul class="jh-pro__tiles">
							{foreach from=$jhP.tiles item=jhTile}
								<li>
									{if $jhTile.url}<a class="jh-tile" href="{$jhTile.url|escape}" target="_blank" rel="noopener">{else}<span class="jh-tile">{/if}
										<span class="jh-tile__badge{if $jhTile.badge|count_characters > 2} jh-tile__badge--sm{/if}">{$jhTile.badge|escape}</span>
										<span class="jh-tile__text"><small>{$jhTile.label|escape}</small><b>{$jhTile.value|escape}</b></span>
									{if $jhTile.url}</a>{else}</span>{/if}
								</li>
							{/foreach}
						</ul>
					{/if}
					{if $jhP.showSubmit}
						<div class="jh-pro__submit">
							<a class="jh-pro__submitbtn" href="{$jhP.submitUrl|escape}">
								<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M5 20h14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
								{translate key="plugins.generic.journalHero.submit"}
							</a>
							<p>{translate key="plugins.generic.journalHero.submit.desc"}</p>
							<div class="jh-pro__submitlinks">
								<a href="{$jhP.submitUrl|escape}#authorGuidelines">{translate key="plugins.generic.journalHero.authorGuidelines"}</a>
								<a href="{$jhP.contactUrl|escape}">{translate key="plugins.generic.journalHero.contact"}</a>
							</div>
						</div>
					{/if}
				</div>
			</div>
		</section>
	{else}

	<section class="jh-hero{if $jh.background} jh-hero--image{/if}"{if $jh.background} style="--jh-bg:url('{$jh.background|escape}')"{/if}>
		<div class="jh-wrap jh-hero__grid{if !$jh.covers} jh-hero__grid--single{/if}">
			<div class="jh-hero__main">
				{if $jh.eyebrow}
					<div class="jh-eyebrow">{$jh.eyebrow|escape}</div>
				{/if}
				<h2 class="jh-title">{$jh.title|escape}</h2>
				{if $jh.description}
					<p class="jh-desc">{$jh.description|escape}</p>
				{/if}

				{if $jh.showFeatures}
					<ul class="jh-features">
						<li>
							<span class="jh-ficon"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M5 4h11l3 3v13H5z M9 10h6M9 14h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
							<span><b>{translate key="plugins.generic.journalHero.feature.peer"}</b><small>{translate key="plugins.generic.journalHero.feature.peer.desc"}</small></span>
						</li>
						<li>
							<span class="jh-ficon"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
							<span><b>{translate key="plugins.generic.journalHero.feature.oa"}</b><small>{translate key="plugins.generic.journalHero.feature.oa.desc"}</small></span>
						</li>
						<li>
							<span class="jh-ficon"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
							<span><b>{translate key="plugins.generic.journalHero.feature.digital"}</b><small>{translate key="plugins.generic.journalHero.feature.digital.desc"}</small></span>
						</li>
					</ul>
				{/if}

				{if $jh.rankValue || $jh.printIssn || $jh.onlineIssn}
					<div class="jh-chips">
						{if $jh.rankValue}
							{if $jh.rankUrl}
								<a class="jh-rank" href="{$jh.rankUrl|escape}" target="_blank" rel="noopener"><span class="jh-rank__label">{$jh.rankLabel|escape}</span><span class="jh-rank__value">{$jh.rankValue|escape}</span></a>
							{else}
								<span class="jh-rank"><span class="jh-rank__label">{$jh.rankLabel|escape}</span><span class="jh-rank__value">{$jh.rankValue|escape}</span></span>
							{/if}
						{/if}
						{if $jh.printIssn}
							{if $jh.printIssnUrl}<a class="jh-chip" href="{$jh.printIssnUrl|escape}" target="_blank" rel="noopener">p-ISSN {$jh.printIssn|escape}</a>{else}<span class="jh-chip">p-ISSN {$jh.printIssn|escape}</span>{/if}
						{/if}
						{if $jh.onlineIssn}
							{if $jh.onlineIssnUrl}<a class="jh-chip" href="{$jh.onlineIssnUrl|escape}" target="_blank" rel="noopener">e-ISSN {$jh.onlineIssn|escape}</a>{else}<span class="jh-chip">e-ISSN {$jh.onlineIssn|escape}</span>{/if}
						{/if}
					</div>
				{/if}
			</div>

			{if $jh.covers}
				<div class="jh-covers" data-jh-carousel>
					<div class="jh-covers__track">
						{foreach from=$jh.covers item=jhCover name=jhCovers}
							<a class="jh-cover jh-cover--{$smarty.foreach.jhCovers.index%3}{if $jhCover.image} jh-cover--img{/if}" href="{$jhCover.url|escape}">
								{if $jhCover.image}
									<img src="{$jhCover.image|escape}" alt="{$jhCover.alt|default:$jhCover.label|escape}">
								{else}
									<span class="jh-cover__journal">{$jh.journalName|escape}</span>
									<span class="jh-cover__title">{$jhCover.title|escape}</span>
								{/if}
								<span class="jh-cover__label">{$jhCover.label|escape}</span>
							</a>
						{/foreach}
					</div>
					{if $jh.covers|@count > 3}
						<div class="jh-covers__nav">
							<button type="button" data-jh-prev aria-label="{translate|escape key="plugins.generic.journalHero.prev"}">&lsaquo;</button>
							<div class="jh-covers__dots" data-jh-dots></div>
							<button type="button" data-jh-next aria-label="{translate|escape key="plugins.generic.journalHero.next"}">&rsaquo;</button>
						</div>
					{/if}
				</div>
			{/if}
		</div>
	</section>
	{/if}

	{if $jh.showStrip}
		<section class="jh-strip">
			<div class="jh-wrap jh-strip__grid">
				<div class="jh-strip__item">
					<span class="jh-strip__icon"><svg viewBox="0 0 24 24" width="21" height="21" aria-hidden="true"><path d="M7 18a5 5 0 01-.6-10 6 6 0 0111.4 1.6A4.2 4.2 0 0117.5 18zM12 10v6M9.5 12.5L12 10l2.5 2.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
					<div><b>{translate key="plugins.generic.journalHero.strip.ojs"}</b><p>{translate key="plugins.generic.journalHero.strip.ojs.desc"}</p></div>
				</div>
				<a class="jh-strip__item" href="{$jh.editorialUrl|escape}">
					<span class="jh-strip__icon"><svg viewBox="0 0 24 24" width="21" height="21" aria-hidden="true"><circle cx="9" cy="8" r="3.2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17" cy="9" r="2.4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 19c.6-3.2 3-5 6-5s5.4 1.8 6 5M15 14.5c2.6-.4 4.6 1.2 5.2 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
					<div><b>{translate key="plugins.generic.journalHero.strip.editors"}</b><p>{translate key="plugins.generic.journalHero.strip.editors.desc"}</p></div>
				</a>
				<a class="jh-strip__item" href="{$jh.aboutUrl|escape}">
					<span class="jh-strip__icon"><svg viewBox="0 0 24 24" width="21" height="21" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z M8.5 12l2.5 2.5 4.5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg></span>
					<div><b>{translate key="plugins.generic.journalHero.strip.quality"}</b><p>{translate key="plugins.generic.journalHero.strip.quality.desc"}</p></div>
				</a>
			</div>
			<svg class="jh-wave" viewBox="0 0 1440 70" preserveAspectRatio="none" aria-hidden="true">
				<path d="M0 62 C 420 66 900 36 1440 6 L1440 70 L0 70 Z" class="jh-wave__fill"/>
				<path d="M0 62 C 420 66 900 36 1440 6" class="jh-wave__line"/>
			</svg>
		</section>
	{/if}
</div>
