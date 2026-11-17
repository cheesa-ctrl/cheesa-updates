{**
 * Blok sidebar CHEESA Editorial Pick.
 * $editorialPick = Submission[] ; $issueDao = IssueDAO
 *}
<div class="pkp_block block_cheesa_editorial_pick">
	<h2 class="title">Editorial Pick</h2>
	<div class="content">
		<ul class="chep-list">
		{foreach $editorialPick as $ep}
			{assign var="pub" value=$ep->getCurrentPublication()}
			{assign var="authors" value=$pub->getData('authors')}
			{assign var="issue" value=$issueDao->getById($pub->getData('issueId'))}
			<li class="chep-item">
				<a class="chep-title" href="{url page="article" op="view" path=$ep->getBestId()}">{$pub->getLocalizedData('title')|escape}</a>
				{if $pub->getData('datePublished')}
				<div class="chep-meta"><span class="fa fa-fw fa-calendar" aria-hidden="true"></span> {$pub->getData('datePublished')|date_format:$dateFormatLong}</div>
				{/if}
				{if $authors}
				<div class="chep-meta"><i class="fa fa-fw fa-user" aria-hidden="true"></i> {$authors[0]->getFullName()|escape}{if $authors|@count > 1} dkk.{/if}</div>
				{/if}
				{if $issue}
				<div class="chep-meta"><a href="{url page="issue" op="view" path=$pub->getData('issueId')}"><i class="fa fa-fw fa-book" aria-hidden="true"></i> {$issue->getIssueIdentification()|escape}</a></div>
				{/if}
			</li>
		{/foreach}
		</ul>
	</div>
</div>

{literal}
<style>
	.block_cheesa_editorial_pick .chep-list { list-style: none; margin: 0; padding: 0; }
	.block_cheesa_editorial_pick .chep-item { padding: 10px 0; border-bottom: 1px solid var(--nv-line, #e3e7ef); }
	.block_cheesa_editorial_pick .chep-item:last-child { border-bottom: 0; }
	.block_cheesa_editorial_pick .chep-title { display: inline-block; font-weight: 700; font-size: 13.5px; line-height: 1.35; color: var(--nv-navy, #030271); text-decoration: none; }
	.block_cheesa_editorial_pick .chep-title:hover { color: var(--nv-accent, #ed991f); }
	.block_cheesa_editorial_pick .chep-meta { font-size: 12px; color: var(--nv-muted, #687089); margin-top: 3px; }
	.block_cheesa_editorial_pick .chep-meta .fa { color: var(--nv-accent, #ed991f); }
	.block_cheesa_editorial_pick .chep-meta a { color: var(--nv-muted, #687089); text-decoration: none; }
	.block_cheesa_editorial_pick .chep-meta a:hover { color: var(--nv-navy, #030271); text-decoration: underline; }
</style>
{/literal}
