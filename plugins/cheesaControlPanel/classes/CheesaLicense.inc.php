<?php

/**
 * CheesaLicense — pemeriksa lisensi CHEESA (salinan identik ada di setiap plugin CHEESA).
 *
 * Berkas cheesa.lic di folder plugin berisi: base64(JSON) "." base64(tanda tangan RSA-SHA256).
 * JSON: {"licensee": "...", "hosts": ["journal-ft.unipma.ac.id"], "journals": ["cheesa"], "issued": "YYYY-MM-DD"}
 * Lisensi hanya bisa dibuat dengan kunci privat CHEESA, sehingga tidak bisa dipalsukan.
 * Yang dicocokkan: nama host (tanpa www., port, http/https) dan path jurnal — bukan URL lengkap.
 * localhost / 127.0.0.1 selalu diizinkan untuk uji coba.
 */

if (!class_exists('CheesaLicense')) {

class CheesaLicense {

	const PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAklKhD6t65TnvxqlpApQe
xAmqXJcqha4eKUnFny9ezlnZfPitAHj2KPJvRTYRpyAUWIZlNKuSNCamzTSNAPyP
0hoqFGL+5cPtsyL+vLXOus82gY/kRmvS0bZQRVvZSVLZ8pDT+ZZGI0VuTjNkyQDn
ftvkjwF1JPkvIEzRjUEbPSmFMD3qhmyBqUjXfn6WicGchGK2urS6WvbLtaIDYDf8
h6q42n8OvwiM2GqagXGjHZsd8WiMo4Rp84BT8uqifnXkwFLnGxrI9GHk9S52fPZt
9Glk3fIscsGXI61WEQqT+cunlBIWfNHGqRc+aG7h/5elA6XF/MmNM0oz2vSXJWdl
mlwGVkfQfwnc0qgaDXIUPBzOgREO0dvwSSRz8i7bExm0YMEdjoM6w/sfqP65amlz
uj5S5iOVE9Js56JS8OL4x6ViksJ4TTOTBj6Mtf2xSyvyqBGvepk1u/B3B7h8Zhlu
LduiLIoaao/+XXEGlDGuARmdfI+yS+psq/g5AG0FTT9NAgMBAAE=
-----END PUBLIC KEY-----
PEM;

	const DEV_HOSTS = ['localhost', '127.0.0.1', '::1'];

	private static $cache = [];

	/** @return array|null isi lisensi bila tanda tangannya sah */
	public static function read($pluginDir) {
		$file = rtrim($pluginDir, '/\\') . '/cheesa.lic';
		if (array_key_exists($file, self::$cache)) return self::$cache[$file];
		$data = null;
		$raw = is_file($file) ? trim((string) file_get_contents($file)) : '';
		if ($raw && strpos($raw, '.') !== false && function_exists('openssl_verify')) {
			list($p, $s) = explode('.', $raw, 2);
			$json = base64_decode($p, true);
			$sig = base64_decode($s, true);
			if ($json !== false && $sig !== false && openssl_verify($json, $sig, self::PUBLIC_KEY, OPENSSL_ALGO_SHA256) === 1) {
				$d = json_decode($json, true);
				if (is_array($d) && !empty($d['hosts']) && is_array($d['hosts'])) $data = $d;
			}
		}
		return self::$cache[$file] = $data;
	}

	public static function normHost($host) {
		$host = strtolower(trim((string) $host));
		$host = preg_replace('#^\[|\](:\d+)?$#', '', $host);           // [::1]:80
		if (substr_count($host, ':') === 1) $host = explode(':', $host)[0]; // port
		return preg_replace('#^www\.#', '', $host);
	}

	/**
	 * @param string $pluginDir folder plugin
	 * @param string $host host permintaan
	 * @param string|null $journalPath path jurnal (null = halaman tingkat situs)
	 * @return string '' bila sah, atau alasan bila tidak
	 */
	public static function check($pluginDir, $host, $journalPath = null) {
		$host = self::normHost($host);
		if ($host === '' || in_array($host, self::DEV_HOSTS, true)) return '';
		$lic = self::read($pluginDir);
		if (!$lic) return 'berkas lisensi (cheesa.lic) tidak ada atau tidak sah';
		$hosts = array_map([__CLASS__, 'normHost'], $lic['hosts']);
		if (!in_array($host, $hosts, true)) return 'lisensi tidak berlaku untuk domain ' . $host;
		$journals = isset($lic['journals']) && is_array($lic['journals']) ? array_map('strtolower', $lic['journals']) : [];
		if ($journalPath !== null && $journals && !in_array(strtolower($journalPath), $journals, true)) return 'lisensi tidak berlaku untuk jurnal ' . $journalPath;
		return '';
	}

	/** Pemeriksaan dengan permintaan OJS saat ini. Command line (alat upgrade OJS) tidak diblokir. */
	public static function checkRequest($pluginDir) {
		if (PHP_SAPI === 'cli' && empty($_SERVER['HTTP_HOST'])) return '';   // alat command line OJS (upgrade, dll.)
		try {
			$request = Application::get()->getRequest();
			$host = method_exists($request, 'getServerHost') ? $request->getServerHost(null, false) : ($_SERVER['HTTP_HOST'] ?? '');
			$context = $request->getContext();
			return self::check($pluginDir, $host, $context ? $context->getPath() : null);
		} catch (\Throwable $e) {
			return self::check($pluginDir, $_SERVER['HTTP_HOST'] ?? '', null);
		}
	}

	/** Catatan untuk daftar plugin (hanya muncul bila tidak sah). */
	public static function notice($reason) {
		return $reason ? ' <strong style="color:#c62828">[Tidak aktif: ' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '. Plugin ini hanya untuk jurnal CHEESA.]</strong>' : '';
	}
}

}
