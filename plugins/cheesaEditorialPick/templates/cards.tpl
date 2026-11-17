{**
 * Baris kartu Editorial Pick di beranda.
 * $editorialPick = Submission[] ; $issueDao = IssueDAO
 *}
<section class="chep-cards" aria-label="Editorial Pick">
	<h2 class="chep-cards-title">Editorial Pick</h2>
	<div class="chep-grid">
		{foreach $editorialPick as $ep}
			{assign var="pub" value=$ep->getCurrentPublication()}
			{assign var="authors" value=$pub->getData('authors')}
			{assign var="issue" value=$issueDao->getById($pub->getData('issueId'))}
			{assign var="cover" value=$pub->getLocalizedCoverImageUrl($ep->getData('contextId'))}
			<article class="chep-card">
				<a class="chep-card-cover" href="{url page="article" op="view" path=$ep->getBestId()}">
					{if $cover}<img src="{$cover|escape}" alt="" loading="lazy">{else}<span class="chep-card-noimg">CHEESA</span>{/if}
				</a>
				<div class="chep-card-body">
					<a class="chep-card-title" href="{url page="article" op="view" path=$ep->getBestId()}">{$pub->getLocalizedData('title')|escape}</a>
					<div class="chep-card-meta">
						{if $pub->getData('datePublished')}<div><span class="fa fa-fw fa-calendar" aria-hidden="true"></span> {$pub->getData('datePublished')|date_format:$dateFormatLong}</div>{/if}
						{if $authors}<div><i class="fa fa-fw fa-user" aria-hidden="true"></i> {$authors[0]->getFullName()|escape}{if $authors|@count > 1} dkk.{/if}</div>{/if}
						{if $issue}<div><a href="{url page="issue" op="view" path=$pub->getData('issueId')}"><i class="fa fa-fw fa-book" aria-hidden="true"></i> {$issue->getIssueIdentification()|escape}</a></div>{/if}
					</div>
				</div>
			</article>
		{/foreach}
	</div>
</section>

{literal}
<style>
	.chep-cards { margin: 28px 0; }
	.chep-cards-title {
		color: var(--nv-navy, #030271); font-weight: 800; font-size: 20px;
		margin: 0 0 14px; padding: 0 0 8px; display: inline-block;
		border-bottom: 3px solid var(--nv-accent, #ed991f);
	}
	.chep-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 16px; }
	.chep-card {
		display: flex; flex-direction: column; background: #fff;
		border: 1px solid var(--nv-line, #e3e7ef); border-radius: var(--nv-radius, 12px);
		box-shadow: 0 2px 8px rgba(3, 2, 113, .06); overflow: hidden;
		transition: transform .15s, box-shadow .15s;
	}
	.chep-card:hover { transform: translateY(-4px); box-shadow: 0 10px 24px rgba(3, 2, 113, .12); }
	.chep-card-cover { display: block; line-height: 0; }
	.chep-card-cover img { width: 100%; height: 150px; object-fit: cover; display: block; }
	.chep-card-noimg {
		display: flex; align-items: center; justify-content: center; height: 150px;
		background: linear-gradient(135deg, var(--nv-navy, #030271), #01014d);
		color: #fff; font-weight: 800; letter-spacing: 1px; font-size: 20px;
	}
	.chep-card-body { padding: 12px 14px; display: flex; flex-direction: column; flex: 1; }
	.chep-card-title {
		color: var(--nv-navy, #030271); font-weight: 700; font-size: 14px; line-height: 1.35; text-decoration: none;
		display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
	}
	.chep-card-title:hover { color: var(--nv-accent, #ed991f); }
	.chep-card-meta { margin-top: auto; padding-top: 10px; font-size: 12px; color: var(--nv-muted, #687089); }
	.chep-card-meta > div { display: flex; align-items: baseline; gap: 6px; margin-top: 3px; }
	.chep-card-meta .fa { color: var(--nv-accent, #ed991f); width: 13px; text-align: center; flex: 0 0 auto; }
	.chep-card-meta a { color: var(--nv-muted, #687089); text-decoration: none; }
	.chep-card-meta a:hover { color: var(--nv-navy, #030271); text-decoration: underline; }
</style>
{/literal}
