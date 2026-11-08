<?php
/**
 * cheesa-release.php — membuat paket update CHEESA (jalankan di komputer Anda, bukan di server OJS).
 *
 * Pemakaian (Windows / XAMPP):
 *   C:\xampp\php\php.exe cheesa-release.php <folder-sumber> <kunci-privat.pem> <folder-keluaran>
 *
 *   <folder-sumber>   folder berisi folder plugin OJS mana pun (tema, generic, block, dll.), dikenali dari version.xml.
 *                     Plugin baru cukup ditaruh di sini. Yang tidak ada di sini tetap diambil dari manifest lama.
 *   <kunci-privat.pem> kunci privat CHEESA (JANGAN diunggah ke GitHub)
 *   <folder-keluaran> folder repo update (berisi manifest.json lama, bila ada)
 *
 *   [lisensi.lic]     opsional: berkas cheesa.lic yang disisipkan ke plugin CHEESA (yang memakai CheesaLicense)
 *
 * Hasil di folder keluaran: <nama>-<versi>.tar.gz, manifest.json, manifest.json.sig
 * Plugin yang versinya sama dengan manifest tidak dibuat ulang; paket versi lama dihapus.
 * Dipakai juga oleh GitHub Actions (rilis otomatis).
 * Lalu unggah ketiganya ke GitHub (lihat PANDUAN).
 */

const LABELS = ['cheesaNovelty' => 'CHEESA Theme', 'journalWidgets' => 'Journal Portal Widgets', 'crossmarkButton' => 'Crossmark Button', 'cheesaControlPanel' => 'CHEESA Control Panel'];
const ALLOWED = ['themes', 'generic', 'blocks', 'importexport', 'pubIds', 'reports', 'oaiMetadataFormats', 'paymethod'];

if (PHP_SAPI !== 'cli') exit("Jalankan dari command line.\n");
if ($argc < 4) exit("Pemakaian: php cheesa-release.php <folder-sumber> <kunci-privat.pem> <folder-keluaran>\n");
[$_, $src, $keyFile, $out] = $argv;
$licFile = $argv[4] ?? '';
$lic = ($licFile && is_file($licFile)) ? trim(file_get_contents($licFile)) . "\n" : '';
foreach (['phar' => 'PharData', 'openssl' => 'openssl_sign', 'zlib' => 'gzopen'] as $ext => $sym) {
	if (!class_exists($sym) && !function_exists($sym)) exit("Ekstensi PHP \"$ext\" belum aktif.\n");
}
if (ini_get('phar.readonly')) exit("Jalankan dengan:  php -d phar.readonly=0 cheesa-release.php ...\n");
$key = openssl_pkey_get_private(file_get_contents($keyFile));
if (!$key) { fwrite(STDERR, "Kunci privat tidak bisa dibaca.\n"); exit(1); }
@mkdir($out, 0775, true);

$manifestFile = "$out/manifest.json";
$manifest = is_file($manifestFile) ? json_decode(file_get_contents($manifestFile), true) : null;
if (!is_array($manifest)) $manifest = ['packages' => []];
$changed = !is_file("$manifestFile.sig");

foreach (glob("$src/*", GLOB_ONLYDIR) as $dir) {
	$name = basename($dir);
	$vx = @file_get_contents("$dir/version.xml");
	if (!$vx || !preg_match('#<release>\s*([0-9.]+)\s*</release>#', $vx, $m)) { echo "Lewati $name: version.xml tidak terbaca.\n"; continue; }
	$ver = $m[1];
	if (!preg_match('#<application>\s*([A-Za-z0-9_]+)\s*</application>#', $vx, $a) || $a[1] !== $name) { echo "Lewati $name: <application> di version.xml harus sama dengan nama folder.\n"; continue; }
	if (!preg_match('#<type>\s*plugins\.([A-Za-z]+)\s*</type>#', $vx, $t) || !in_array($t[1], ALLOWED, true)) { echo "Lewati $name: jenis plugin tidak didukung.\n"; continue; }
	$category = $t[1];
	if (isset($manifest['packages'][$name]) && version_compare($ver, $manifest['packages'][$name]['version'], '<')) {
		echo "Lewati $name: versi $ver lebih lama dari manifest ({$manifest['packages'][$name]['version']}).\n"; continue;
	}
	$file = "$name-$ver.tar.gz";
	if (isset($manifest['packages'][$name]) && $manifest['packages'][$name]['version'] === $ver && is_file("$out/$file")) {
		echo "Tetap $name $ver (tidak berubah)\n"; continue;
	}
	$tar = "$out/$name-$ver.tar";
	@unlink($tar); @unlink("$out/$file");
	$phar = new PharData($tar);
	$base = realpath($dir);
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
	foreach ($it as $f) {
		$rel = str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
		if (preg_match('#(^|/)(\.git|\.DS_Store|Thumbs\.db)#', $rel)) continue;
		if ($rel === 'cheesa.lic' && $lic) continue;
		$phar->addFile($f->getPathname(), "$name/$rel");
	}
	if ($lic && is_file("$base/classes/CheesaLicense.inc.php")) $phar->addFromString("$name/cheesa.lic", $lic);
	$phar->compress(Phar::GZ);
	unset($phar);
	@unlink($tar);
	$manifest['packages'][$name] = [
		'category' => $category,
		'label' => LABELS[$name] ?? trim(ucfirst(preg_replace('/(?<!^)([A-Z])/', ' $1', $name))),
		'version' => $ver,
		'file' => $file,
		'sha256' => hash_file('sha256', "$out/$file"),
		'size' => filesize("$out/$file"),
		'released' => date('Y-m-d'),
	];
	foreach (glob("$out/$name-*.tar.gz") as $oldPkg) if (basename($oldPkg) !== $file && preg_match('#^' . preg_quote($name, '#') . '-[0-9.]+\.tar\.gz$#', basename($oldPkg))) @unlink($oldPkg);
	$changed = true;
	echo "OK  $name $ver -> $file\n";
}
if (!$changed) { echo "Tidak ada plugin baru / versi baru. Manifest tidak diubah.\n"; exit(0); }
ksort($manifest['packages']);
$manifest['generated'] = gmdate('c');
$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
openssl_sign($json, $sig, $key, OPENSSL_ALGO_SHA256);
file_put_contents($manifestFile, $json);
file_put_contents("$manifestFile.sig", base64_encode($sig) . "\n");
echo "manifest.json & manifest.json.sig ditandatangani. Unggah berkas baru di $out ke GitHub.\n";
