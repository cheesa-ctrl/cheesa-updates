<?php

/**
 * @file plugins/generic/cheesaGuard/classes/LoginThrottle.inc.php
 *
 * Pembatas percobaan login (anti brute-force) berbasis IP. Menyimpan cap waktu
 * percobaan per-IP di sebuah berkas JSON di dalam files_dir, memangkas yang
 * kedaluwarsa, dan mengunci sementara bila melewati ambang.
 */

class CheesaLoginThrottle {

	const MAX = 10;       // percobaan maksimum dalam jendela waktu
	const WINDOW = 600;   // jendela waktu (detik) = 10 menit

	private static function file() {
		$d = Config::getVar('files', 'files_dir') . '/cheesaGuard';
		if (!is_dir($d)) @mkdir($d, 0750, true);
		return $d . '/throttle.json';
	}

	private static function load() {
		$f = self::file();
		if (!is_file($f)) return [];
		$m = json_decode((string) file_get_contents($f), true);
		return is_array($m) ? $m : [];
	}

	private static function save(array $m) {
		@file_put_contents(self::file(), json_encode($m), LOCK_EX);
	}

	private static function key($ip) {
		return substr(sha1((string) $ip), 0, 16);
	}

	/** Buang cap waktu & kunci kedaluwarsa agar berkas tidak membengkak. */
	private static function prune(array $m, $now) {
		foreach ($m as $k => $ts) {
			$ts = array_values(array_filter((array) $ts, function ($t) use ($now) { return $t > $now - self::WINDOW; }));
			if ($ts) $m[$k] = $ts; else unset($m[$k]);
		}
		return $m;
	}

	/** Sisa detik sampai tidak terkunci (0 = tidak terkunci). */
	public static function remaining($ip) {
		$now = time();
		$m = self::prune(self::load(), $now);
		$k = self::key($ip);
		$ts = $m[$k] ?? [];
		if (count($ts) < self::MAX) return 0;
		return max(0, min($ts) + self::WINDOW - $now);
	}

	/** Catat satu percobaan dari IP ini. */
	public static function hit($ip) {
		$now = time();
		$m = self::prune(self::load(), $now);
		$k = self::key($ip);
		$ts = $m[$k] ?? [];
		$ts[] = $now;
		$m[$k] = $ts;
		self::save($m);
	}

	/** Bersihkan hitungan IP ini (dipanggil saat login berhasil). */
	public static function clear($ip) {
		$now = time();
		$m = self::prune(self::load(), $now);
		unset($m[self::key($ip)]);
		self::save($m);
	}
}
