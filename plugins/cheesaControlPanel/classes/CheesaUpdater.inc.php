<?php

/**
 * @file plugins/generic/cheesaControlPanel/classes/CheesaUpdater.inc.php
 *
 * Inti updater CHEESA Control Panel. Tidak bergantung pada OJS, jadi bisa diuji di CLI.
 *
 * Alur update satu paket:
 *   1. manifest.json + manifest.json.sig diunduh; tanda tangan RSA-SHA256 diverifikasi dengan kunci publik bawaan plugin.
 *   2. Paket .tar.gz diunduh; SHA-256 dicocokkan dengan manifest.
 *   3. Isi paket diperiksa (semua berkas di dalam folder <nama>/, tanpa "..", ada version.xml dengan versi yang sama).
 *   4. Folder lama disalin ke folder cadangan, lalu folder baru ditukar masuk. Bila penukaran gagal, folder lama dikembalikan.
 * Tidak memakai perintah `tar` sistem (memakai PharData bawaan PHP).
 */

class CheesaUpdater {

	const CATEGORIES = ['themes', 'generic', 'blocks', 'importexport', 'pubIds', 'reports', 'oaiMetadataFormats', 'paymethod'];

	/** @var string folder plugins/ OJS */
	public $pluginsDir;
	/** @var string folder kerja di plugins/ (sistem berkas sama, supaya rename atomik) */
	public $stageDir;
	/** @var string folder cadangan (mis. di files_dir) */
	public $backupDir;
	/** @var string kunci publik PEM */
	public $publicKey;
	/** @var callable function(string $url): string — mengunduh isi URL, melempar Exception bila gagal */
	public $fetch;
	/**
	 * @var callable|null function(string $newDir, string $category, string $name, bool $isNew): void
	 * Bila diisi (oleh plugin OJS), dipakai untuk plugin baru dan untuk update yang membawa upgrade database,
	 * supaya installer OJS menjalankan skema/migrasi plugin. Melempar Exception bila gagal.
	 */
	public $ojsInstaller = null;
	/** @var int jumlah cadangan yang disimpan per paket */
	public $keepBackups = 3;

	public function __construct($pluginsDir, $stageDir, $backupDir, $publicKey, callable $fetch) {
		$this->pluginsDir = rtrim($pluginsDir, '/\\');
		$this->stageDir = rtrim($stageDir, '/\\');
		$this->backupDir = rtrim($backupDir, '/\\');
		$this->publicKey = $publicKey;
		$this->fetch = $fetch;
	}

	/* ---------------- manifest ---------------- */

	/** Unduh & verifikasi manifest. @return array ['generated'=>..., 'packages'=>[name=>pkg]] */
	public function fetchManifest($manifestUrl) {
		$json = call_user_func($this->fetch, $manifestUrl);
		$sig = trim(call_user_func($this->fetch, $manifestUrl . '.sig'));
		if (!$this->verify($json, $sig)) throw new Exception('Tanda tangan manifest tidak valid. Paket tidak dipercaya, update dibatalkan.');
		$m = json_decode($json, true);
		if (!is_array($m) || !isset($m['packages']) || !is_array($m['packages'])) throw new Exception('Format manifest tidak dikenali.');
		foreach ($m['packages'] as $name => &$p) {
			$this->validatePackage($name, $p);
			$p['url'] = $this->resolveUrl($manifestUrl, $p['file']);
		}
		return $m;
	}

	public function verify($data, $sigB64) {
		$sig = base64_decode($sigB64, true);
		if ($sig === false || !$this->publicKey) return false;
		return openssl_verify($data, $sig, $this->publicKey, OPENSSL_ALGO_SHA256) === 1;
	}

	private function validatePackage($name, $p) {
		if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $name)) throw new Exception("Nama paket tidak valid: $name");
		if (!isset($p['category']) || !in_array($p['category'], self::CATEGORIES, true)) throw new Exception("Kategori paket $name tidak valid.");
		if (!isset($p['version']) || !preg_match('/^\d+(\.\d+){1,3}$/', $p['version'])) throw new Exception("Versi paket $name tidak valid.");
		if (!isset($p['sha256']) || !preg_match('/^[a-f0-9]{64}$/', $p['sha256'])) throw new Exception("Checksum paket $name tidak valid.");
		if (!isset($p['file']) || !preg_match('#^[A-Za-z0-9_.\-/:]+\.tar\.gz$#', $p['file'])) throw new Exception("Nama berkas paket $name tidak valid.");
	}

	/** Berkas paket boleh relatif terhadap lokasi manifest (mis. "cheesaNovelty-1.4.1.0.tar.gz"). */
	public function resolveUrl($manifestUrl, $file) {
		if (preg_match('#^https://#i', $file)) return $file;
		return substr($manifestUrl, 0, strrpos($manifestUrl, '/') + 1) . ltrim($file, '/');
	}

	/* ---------------- versi terpasang ---------------- */

	public function pluginPath($category, $name) {
		return $this->pluginsDir . '/' . $category . '/' . $name;
	}

	/** @return string|null versi dari version.xml folder plugin, null bila belum terpasang */
	public function installedVersion($category, $name) {
		$f = $this->pluginPath($category, $name) . '/version.xml';
		return is_file($f) ? $this->readRelease($f) : null;
	}

	public function readRelease($versionXml) {
		$x = @file_get_contents($versionXml);
		return ($x && preg_match('#<release>\s*([0-9.]+)\s*</release>#', $x, $m)) ? $m[1] : null;
	}

	public function readApplication($versionXml) {
		$x = @file_get_contents($versionXml);
		return ($x && preg_match('#<application>\s*([A-Za-z0-9_]+)\s*</application>#', $x, $m)) ? $m[1] : null;
	}

	public static function isNewer($remote, $local) {
		return $local === null || version_compare($remote, $local, '>');
	}

	/* ---------------- pemasangan ---------------- */

	/** Periksa izin tulis sebelum mulai. @return string[] daftar masalah */
	public function preflight($category) {
		$problems = [];
		if (!class_exists('PharData')) $problems[] = 'Ekstensi PHP "phar" tidak aktif.';
		if (!function_exists('openssl_verify')) $problems[] = 'Ekstensi PHP "openssl" tidak aktif.';
		if (!function_exists('gzopen')) $problems[] = 'Ekstensi PHP "zlib" tidak aktif.';
		$dir = $this->pluginsDir . '/' . $category;
		if (!is_writable($dir)) $problems[] = "Folder $dir tidak bisa ditulis oleh server web.";
		foreach ([$this->stageDir, $this->backupDir] as $d) {
			if (!is_dir($d) && !@mkdir($d, 0775, true)) $problems[] = "Folder $d tidak bisa dibuat.";
			elseif (!is_writable($d)) $problems[] = "Folder $d tidak bisa ditulis.";
		}
		return $problems;
	}

	/**
	 * Pasang/perbarui satu paket dari manifest yang sudah diverifikasi.
	 * @return array ['from' => versi lama|null, 'to' => versi baru, 'backup' => path cadangan|null]
	 */
	public function install($name, array $pkg) {
		$category = $pkg['category'];
		if ($problems = $this->preflight($category)) throw new Exception(implode(' ', $problems));

		$token = $name . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
		$work = $this->stageDir . '/' . $token;
		if (!@mkdir($work, 0775, true)) throw new Exception('Folder kerja tidak bisa dibuat.');
		try {
			// 1) unduh + checksum
			$data = call_user_func($this->fetch, $pkg['url']);
			if (!hash_equals($pkg['sha256'], hash('sha256', $data))) throw new Exception('Checksum paket tidak cocok (unduhan rusak atau paket diubah).');
			$tgz = $work . '/pkg.tar.gz';
			file_put_contents($tgz, $data);

			// 2) periksa isi lalu ekstrak
			$extract = $work . '/x';
			$this->safeExtract($tgz, $extract, $name);
			$newDir = $extract . '/' . $name;
			$vx = $newDir . '/version.xml';
			if (!is_file($vx)) throw new Exception('Paket tidak berisi version.xml.');
			if ($this->readApplication($vx) !== $name) throw new Exception('Isi paket tidak sesuai nama paket.');
			if ($this->readRelease($vx) !== $pkg['version']) throw new Exception('Versi di dalam paket tidak sama dengan manifest.');

			// 3) cadangan + tukar folder
			$target = $this->pluginPath($category, $name);
			$from = $this->installedVersion($category, $name);
			$backup = null;
			$viaOjs = false;
			$needsDb = is_file($newDir . '/upgrade.xml');
			if (!is_dir($target) && $this->ojsInstaller) {
				// plugin baru: lewat installer OJS (membuat tabel database bila plugin membutuhkannya)
				call_user_func($this->ojsInstaller, $newDir, $category, $name, true);
				$viaOjs = true;
			} elseif (is_dir($target) && $needsDb && $this->ojsInstaller) {
				// update dengan perubahan database: cadangkan dulu, lalu lewat installer OJS
				$backup = $this->backupDir . '/' . $name . '-' . ($from ?: 'x') . '-' . date('Ymd-His');
				$this->copyDir($target, $backup);
				call_user_func($this->ojsInstaller, $newDir, $category, $name, false);
				$viaOjs = true;
			} elseif (is_dir($target)) {
				$backup = $this->backupDir . '/' . $name . '-' . ($from ?: 'x') . '-' . date('Ymd-His');
				$this->copyDir($target, $backup);
				$old = $work . '/old';
				if (!@rename($target, $old)) throw new Exception("Folder lama $target tidak bisa dipindahkan (periksa izin tulis).");
				if (!@rename($newDir, $target)) {
					@rename($old, $target);   // kembalikan
					throw new Exception('Folder baru gagal dipasang; versi lama dikembalikan.');
				}
			} else {
				if (!@rename($newDir, $target)) throw new Exception("Folder $target tidak bisa dibuat.");
			}
			$this->pruneBackups($name);
			if (!is_file($target . '/version.xml')) throw new Exception('Pemasangan tidak selesai: ' . $target . ' tidak ditemukan.');
			return ['from' => $from, 'to' => $pkg['version'], 'backup' => $backup, 'new' => $from === null, 'viaOjs' => $viaOjs];
		} finally {
			$this->rmDir($work);
		}
	}

	/** Kembalikan cadangan terakhir sebuah paket. @return string versi yang dipulihkan */
	public function rollback($category, $name) {
		$list = $this->backups($name);
		if (!$list) throw new Exception('Tidak ada cadangan untuk ' . $name . '.');
		$src = end($list);
		$target = $this->pluginPath($category, $name);
		$tmp = $this->stageDir . '/rb-' . $name . '-' . bin2hex(random_bytes(3));
		$this->copyDir($src, $tmp);
		$old = $tmp . '-old';
		if (is_dir($target) && !@rename($target, $old)) { $this->rmDir($tmp); throw new Exception('Folder plugin tidak bisa dipindahkan.'); }
		if (!@rename($tmp, $target)) { @rename($old, $target); $this->rmDir($tmp); throw new Exception('Pemulihan gagal; versi saat ini dipertahankan.'); }
		$this->rmDir($old);
		$this->rmDir($src);
		return $this->installedVersion($category, $name);
	}

	public function backups($name) {
		$list = glob($this->backupDir . '/' . $name . '-*', GLOB_ONLYDIR) ?: [];
		sort($list);
		return $list;
	}

	private function pruneBackups($name) {
		$list = $this->backups($name);
		while (count($list) > $this->keepBackups) $this->rmDir(array_shift($list));
	}

	/** Ekstrak tar.gz setelah memastikan setiap entri berada di dalam folder <nama>/ dan tidak ada symlink / "..". */
	public function safeExtract($tgz, $dest, $name) {
		$phar = new PharData($tgz);
		$prefix = 'phar://' . str_replace('\\', '/', realpath($tgz)) . '/';
		$count = 0;
		foreach (new RecursiveIteratorIterator($phar, RecursiveIteratorIterator::SELF_FIRST) as $path => $info) {
			$rel = substr(str_replace('\\', '/', $path), strlen($prefix));
			if ($rel === false || $rel === '') continue;
			$parts = explode('/', $rel);
			if ($parts[0] !== $name || in_array('..', $parts, true) || in_array('', array_slice($parts, 0, -1), true)) {
				throw new Exception('Paket berisi berkas di luar folder ' . $name . ' (' . $rel . ').');
			}
			if ($info->isLink()) throw new Exception('Paket berisi symlink, ditolak.');
			if (++$count > 5000) throw new Exception('Paket terlalu banyak berkas.');
		}
		if (!$count) throw new Exception('Paket kosong.');
		@mkdir($dest, 0775, true);
		$phar->extractTo($dest, null, true);
		unset($phar);
	}

	/* ---------------- utilitas berkas ---------------- */

	public function copyDir($src, $dst) {
		@mkdir($dst, 0775, true);
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($it as $f) {
			$to = $dst . '/' . substr($f->getPathname(), strlen($src) + 1);
			if ($f->isDir()) { @mkdir($to, 0775, true); }
			elseif (!@copy($f->getPathname(), $to)) throw new Exception('Gagal menyalin cadangan: ' . $f->getPathname());
		}
	}

	public function rmDir($dir) {
		if (!is_dir($dir)) return;
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($it as $f) { $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
		@rmdir($dir);
	}
}
