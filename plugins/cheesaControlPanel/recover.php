<?php
/**
 * @file plugins/generic/cheesaControlPanel/recover.php
 *
 * Pemulihan darurat CHEESA Control Panel — bekerja TANPA memuat OJS,
 * supaya versi lama tetap bisa dikembalikan walau situs/OJS error setelah update (tanpa cPanel/FTP).
 * Dilindungi kode pemulihan acak yang ditampilkan di panel (hanya untuk Site Administrator).
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

require_once(__DIR__ . '/classes/CheesaUpdater.inc.php');

$base = dirname(__DIR__, 3);
$cfg = @file_get_contents($base . '/config.inc.php');
if (!$cfg || !preg_match('/^\s*files_dir\s*=\s*"?([^"\r\n]+?)"?\s*$/m', $cfg, $m)) { http_response_code(500); exit('config.inc.php tidak terbaca.'); }
$filesDir = rtrim($m[1], '/\\');
if (!preg_match('#^([A-Za-z]:|/)#', $filesDir)) $filesDir = $base . '/' . $filesDir;
$keyFile = $filesDir . '/cheesaControlPanel/recovery.key';
$key = is_file($keyFile) ? trim(file_get_contents($keyFile)) : '';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
if (strlen($key) < 32 || !hash_equals($key, $token)) {
	usleep(800000);
	http_response_code(403);
	exit('<p style="font-family:sans-serif">Kode pemulihan tidak valid.</p>');
}

$known = ['cheesaNovelty' => 'themes', 'journalWidgets' => 'generic', 'crossmarkButton' => 'generic', 'cheesaControlPanel' => 'generic'];
$u = new CheesaUpdater($base . '/plugins', $base . '/plugins/.cheesacp', $filesDir . '/cheesaControlPanel/backups', '', function () { throw new Exception('offline'); });

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = (string) ($_POST['package'] ?? '');
	$cat = (string) ($_POST['category'] ?? '');
	if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $name) || !in_array($cat, CheesaUpdater::CATEGORIES, true)) $msg = 'Paket tidak valid.';
	else {
		try { $v = $u->rollback($cat, $name); $msg = "$name dikembalikan ke versi $v."; if (function_exists('opcache_reset')) @opcache_reset(); }
		catch (Exception $e) { $msg = 'Gagal: ' . $e->getMessage(); }
	}
}

// daftar paket yang punya cadangan
$rows = [];
foreach (glob($filesDir . '/cheesaControlPanel/backups/*', GLOB_ONLYDIR) ?: [] as $d) {
	$n = explode('-', basename($d))[0];
	if (isset($rows[$n])) { $rows[$n]['count']++; $rows[$n]['last'] = basename($d); continue; }
	$cat = $known[$n] ?? null;
	if (!$cat) foreach (CheesaUpdater::CATEGORIES as $c) if (is_dir("$base/plugins/$c/$n")) { $cat = $c; break; }
	if ($cat) $rows[$n] = ['cat' => $cat, 'count' => 1, 'last' => basename($d), 'now' => $u->installedVersion($cat, $n)];
}
$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pemulihan CHEESA</title>
<style>body{font-family:system-ui,sans-serif;max-width:760px;margin:40px auto;padding:0 16px;color:#1d2140}h1{color:#030271}table{width:100%;border-collapse:collapse}th,td{padding:9px;border-bottom:1px solid #e3e6ef;text-align:left}th{background:#030271;color:#fff}.m{padding:10px 12px;border-left:4px solid #ed991f;background:#fff8ec;margin:0 0 16px}button{padding:6px 12px;border:0;border-radius:4px;background:#030271;color:#fff;font-weight:600;cursor:pointer}</style>
</head><body>
<h1>Pemulihan darurat CHEESA</h1>
<p>Gunakan halaman ini bila situs atau dasbor OJS tidak bisa dibuka setelah update. Tombol di bawah mengembalikan cadangan terakhir.</p>
<?php if ($msg): ?><div class="m"><?= $h($msg) ?> Buka kembali situs Anda (Ctrl+F5).</div><?php endif; ?>
<?php if (!$rows): ?><p>Belum ada cadangan.</p><?php else: ?>
<table><tr><th>Paket</th><th>Versi sekarang</th><th>Cadangan terakhir</th><th></th></tr>
<?php foreach ($rows as $n => $r): ?>
<tr><td><?= $h($n) ?></td><td><?= $h($r['now'] ?: '-') ?></td><td><?= $h($r['last']) ?><br><small><?= (int) $r['count'] ?> cadangan</small></td>
<td><form method="post" onsubmit="return confirm('Kembalikan <?= $h($n) ?> ke cadangan terakhir?')"><input type="hidden" name="token" value="<?= $h($token) ?>"><input type="hidden" name="package" value="<?= $h($n) ?>"><input type="hidden" name="category" value="<?= $h($r['cat']) ?>"><button>Kembalikan</button></form></td></tr>
<?php endforeach; ?></table>
<?php endif; ?>
</body></html>
