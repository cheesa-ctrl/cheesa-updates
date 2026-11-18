{**
 * Baris kartu Editorial Pick di beranda — gaya "deck" bertumpuk.
 * $editorialPick = Submission[] ; $issueDao = IssueDAO
 *}
<section id="chepCards" class="chep" aria-label="Editorial Pick">
	<h2 class="chep-title">Editorial Pick</h2>
	<div class="chep-deck">
		{foreach $editorialPick as $ep name=epl}
			{assign var="pub" value=$ep->getCurrentPublication()}
			{assign var="authors" value=$pub->getData('authors')}
			{assign var="issue" value=$issueDao->getById($pub->getData('issueId'))}
			{assign var="cover" value=$pub->getLocalizedCoverImageUrl($ep->getData('contextId'))}
			<article class="chep-card{if $smarty.foreach.epl.first} is-first{/if}">
				<a class="chep-cover" href="{url page="article" op="view" path=$ep->getBestId()}">
					{if $cover}<img src="{$cover|escape}" alt="" loading="lazy">{else}<span class="chep-noimg">CHEESA</span>{/if}
				</a>
				<div class="chep-body">
					<a class="chep-ctitle" href="{url page="article" op="view" path=$ep->getBestId()}">{$pub->getLocalizedData('title')|escape}</a>
					<div class="chep-meta">
						{if $pub->getData('datePublished')}<div><i class="fa fa-fw fa-calendar" aria-hidden="true"></i><span>{$pub->getData('datePublished')|date_format:$dateFormatLong}</span></div>{/if}
						{if $authors}<div><i class="fa fa-fw fa-user" aria-hidden="true"></i><span>{$authors[0]->getFullName()|escape}{if $authors|@count > 1} dkk.{/if}</span></div>{/if}
						{if $issue}<div><a href="{url page="issue" op="view" path=$pub->getData('issueId')}"><i class="fa fa-fw fa-book" aria-hidden="true"></i><span>{$issue->getIssueIdentification()|escape}</span></a></div>{/if}
					</div>
				</div>
			</article>
		{/foreach}
	</div>
</section>

{literal}
<style>
	.chep { margin: 30px 0; }
	.chep-title {
		color: var(--nv-navy, #030271); font-weight: 800; font-size: 22px;
		margin: 0 0 6px; padding: 0 0 8px; display: inline-block;
		border-bottom: 3px solid var(--nv-accent, #ed991f);
	}
	/* baris deck: kartu menyebar horizontal & saling menimpa */
	.chep-deck {
		display: flex; align-items: stretch;
		overflow-x: auto; overflow-y: visible;
		padding: 34px 34px 40px; margin: -14px -34px 0;
		scroll-snap-type: x proximity;
		scrollbar-color: var(--nv-accent, #ed991f) #e9ecf4;
	}
	.chep-deck::-webkit-scrollbar { height: 8px; }
	.chep-deck::-webkit-scrollbar-thumb { background: var(--nv-accent, #ed991f); border-radius: 8px; }
	.chep-deck::-webkit-scrollbar-track { background: #e9ecf4; border-radius: 8px; }

	.chep-card {
		flex: 0 0 auto; width: 310px; scroll-snap-align: start;
		display: flex; flex-direction: column; background: #fff;
		border-radius: 14px; overflow: hidden;
		box-shadow: 0 6px 16px -6px rgba(3, 2, 113, .28);
		transition: transform .22s ease, box-shadow .22s ease;
		will-change: transform;
	}
	/* setiap kartu (selain pertama) menimpa kartu sebelumnya + bayangan di tepi kiri */
	.chep-card:not(.is-first) {
		margin-inline-start: -150px;
		box-shadow: -26px 0 34px -24px rgba(3, 2, 113, .55);
	}
	/* kartu yang di-hover terangkat, sedikit memutar, dan tampil penuh di atas */
	.chep-card:hover {
		transform: translateY(-16px) rotate(2deg); z-index: 6;
		box-shadow: 0 22px 40px -12px rgba(3, 2, 113, .4);
	}
	/* kartu-kartu setelah yang di-hover bergeser ke kanan supaya yang di-hover terbuka */
	.chep-card:hover ~ .chep-card { transform: translateX(150px); }

	.chep-cover { display: block; line-height: 0; }
	.chep-cover img { width: 100%; height: 168px; object-fit: cover; display: block; }
	.chep-noimg {
		display: flex; align-items: center; justify-content: center; height: 168px;
		background: linear-gradient(135deg, var(--nv-navy, #030271) 0%, #01013f 100%);
		color: #fff; font-weight: 800; letter-spacing: 2px; font-size: 24px;
		position: relative;
	}
	.chep-noimg::after {
		content: ""; position: absolute; left: 18px; right: 18px; bottom: 16px; height: 3px;
		background: var(--nv-accent, #ed991f); border-radius: 3px; opacity: .9;
	}
	.chep-body { padding: 14px 16px 16px; display: flex; flex-direction: column; flex: 1; }
	.chep-ctitle {
		color: var(--nv-navy, #030271); font-weight: 700; font-size: 15px; line-height: 1.38; text-decoration: none;
		display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; min-height: 62px;
	}
	.chep-ctitle:hover { color: var(--nv-accent, #ed991f); }
	.chep-meta { margin-top: 12px; padding-top: 12px; border-top: 1px solid #eef0f6; font-size: 12.5px; color: var(--nv-muted, #687089); }
	.chep-meta > div { display: flex; align-items: center; gap: 7px; margin-top: 5px; }
	.chep-meta .fa { color: var(--nv-accent, #ed991f); width: 13px; text-align: center; flex: 0 0 auto; }
	.chep-meta a { color: var(--nv-muted, #687089); text-decoration: none; }
	.chep-meta a:hover { color: var(--nv-navy, #030271); text-decoration: underline; }

	/* layar sempit: matikan tumpukan, jadikan geser biasa */
	@media (max-width: 767px) {
		.chep-deck { padding: 20px 16px 26px; margin: 0 -16px; gap: 14px; }
		.chep-card { width: 250px; }
		.chep-card:not(.is-first) { margin-inline-start: 0; box-shadow: 0 6px 16px -6px rgba(3, 2, 113, .28); }
		.chep-card:hover { transform: translateY(-6px); }
		.chep-card:hover ~ .chep-card { transform: none; }
	}
	@media (prefers-reduced-motion: reduce) {
		.chep-card, .chep-card:hover, .chep-card:hover ~ .chep-card { transition: none; transform: none; }
		.chep-card:not(.is-first) { margin-inline-start: 0; box-shadow: 0 6px 16px -6px rgba(3, 2, 113, .28); }
		.chep-deck { gap: 14px; }
	}
</style>
<script>
(function () {
	// Pindahkan blok Editorial Pick ke BAWAH Announcement (hook tema menaruhnya di atas).
	function place() {
		var sec = document.getElementById('chepCards');
		if (!sec) return;
		var root = document.querySelector('.page_index_journal') || document.body;
		var anchor = root.querySelector('.ch-sl-all') || root.querySelector('.cmp_announcements');
		if (!anchor) return;                       // tidak ada Announcement: biarkan di tempat
		// naik sampai anchor jadi anak langsung dari root, agar penyisipan rapi
		while (anchor.parentNode && anchor.parentNode !== root && anchor.parentNode !== document.body) anchor = anchor.parentNode;
		if (anchor.nextSibling !== sec) anchor.parentNode.insertBefore(sec, anchor.nextSibling);
	}
	if (document.readyState !== 'loading') place(); else document.addEventListener('DOMContentLoaded', place);
	// jalankan lagi setelah skrip tema selesai membentuk slider pengumuman
	setTimeout(place, 400); setTimeout(place, 1200);
})();
</script>
{/literal}
