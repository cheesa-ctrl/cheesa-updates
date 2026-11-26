{**
 * templates/importPage.tpl
 * Halaman penuh untuk alat Import XML Arsip Review (dibuka dari menu sidebar).
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">{translate key="plugins.generic.reviewArchive.import.tabTitle"}</h1>
	{literal}
	<style>
		#raImport { max-width: 64rem; }
		/* judul kartu (h3) disembunyikan karena sudah ada judul halaman di atas */
		#raImport .ra_card > h3:first-child { display: none; }
		/* setiap opsi satu baris, teks bantuan di bawahnya (menimpa CSS backend) */
		#raImport label.ra_option { display: block !important; margin: .6rem 0 !important; font-weight: 600; }
		#raImport label.ra_option small { display: block !important; margin: .2rem 0 0 1.7rem !important; color: #637381 !important; font-weight: 400; line-height: 1.5; }
		#raImport fieldset { margin: 1.25rem 0 !important; }
		#raImport legend { font-weight: 700; margin-bottom: .35rem; }
		#raImport .ra_actions { margin-top: 1.25rem; }
	</style>
	{/literal}
	{$raTabContent nofilter}
{/block}
