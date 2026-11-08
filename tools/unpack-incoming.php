<?php
/**
 * unpack-incoming.php — membongkar arsip plugin (.tar.gz / .tgz / .zip) di folder incoming/
 * ke plugins/<nama>/, berdasarkan version.xml di dalamnya. Dipakai GitHub Actions.
 *   php -d phar.readonly=0 tools/unpack-incoming.php incoming plugins
 */
const ALLOWED = ['themes', 'generic', 'blocks', 'importexport', 'pubIds', 'reports', 'oaiMetadataFormats', 'paymethod'];
[$_, $in, $out] = $argv + [null, 'incoming', 'plugins'];
@mkdir($out, 0775, true);
$fail = 0;

function rmtree($d) { if (!is_dir($d)) return; foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) { $f->isDir() ? rmdir($f) : unlink($f); } rmdir($d); }

foreach (glob("$in/*") as $arc) {
	$base = basename($arc);
	if (!preg_match('#\.(tar\.gz|tgz|zip)$#i', $base)) continue;
	$tmp = sys_get_temp_dir() . '/inc-' . bin2hex(random_bytes(4));
	try {
		$phar = new PharData($arc);
		foreach (new RecursiveIteratorIterator($phar) as $path => $info) {
			if (strpos($path, '..') !== false) throw new Exception('berisi path ".."');
		}
		$phar->extractTo($tmp, null, true);
		// cari version.xml teratas (paket PKP: <nama>/version.xml)
		$vxs = [];
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS)) as $f) {
			if ($f->getFilename() === 'version.xml') $vxs[] = $f->getPathname();
		}
		usort($vxs, function ($a, $b) { return substr_count($a, '/') - substr_count($b, '/'); });
		if (!$vxs) throw new Exception('tidak berisi version.xml');
		$vx = file_get_contents($vxs[0]);
		if (!preg_match('#<application>\s*([A-Za-z0-9_]+)\s*</application>#', $vx, $a)) throw new Exception('<application> tidak ada di version.xml');
		if (!preg_match('#<type>\s*plugins\.([A-Za-z]+)\s*</type>#', $vx, $t) || !in_array($t[1], ALLOWED, true)) throw new Exception('jenis plugin tidak didukung');
		$name = $a[1];
		$dest = "$out/$name";
		rmtree($dest);
		rename(dirname($vxs[0]), $dest);
		echo "OK  $base -> plugins/$name ({$t[1]})\n";
		unlink($arc);
	} catch (Throwable $e) {
		echo "GAGAL $base: " . $e->getMessage() . "\n";
		$fail++;
	} finally {
		rmtree($tmp);
	}
}
exit($fail ? 1 : 0);
