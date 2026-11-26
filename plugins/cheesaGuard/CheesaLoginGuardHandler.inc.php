<?php

/**
 * @file plugins/generic/cheesaGuard/CheesaLoginGuardHandler.inc.php
 *
 * Membungkus halaman login OJS: memeriksa captcha matematika lokal dan pembatas
 * percobaan (throttle) SEBELUM kredensial diproses, lalu mendelegasikan ke
 * LoginHandler inti. Dirancang gagal-aman: bila captcha tidak sempat ditampilkan
 * (mis. tema tak kompatibel), captcha dilewati sehingga admin tidak terkunci,
 * namun throttle tetap berlaku.
 */

import('lib.pkp.pages.login.LoginHandler');

class CheesaLoginGuardHandler extends LoginHandler {

	function signIn($args, $request) {
		// Gagal-aman: kalau logika pengaman error, lanjut ke login inti agar tidak mengunci siapa pun.
		try {
			require_once(__DIR__ . '/classes/LoginThrottle.inc.php');
			$ip = $request->getRemoteAddr();
			$session = $request->getSession();
			if ($session) {
				// 1) Terkunci karena terlalu banyak percobaan?
				$rem = CheesaLoginThrottle::remaining($ip);
				if ($rem > 0) {
					$session->setSessionVar('cheesaLoginMsg', 'Terlalu banyak percobaan login. Coba lagi dalam ' . (int) ceil($rem / 60) . ' menit.');
					return $this->index($args, $request);
				}

				// 2) Verifikasi captcha (hanya bila captcha memang sempat ditampilkan)
				$ans = $session->getSessionVar('cheesaCaptchaAns');
				if ($ans !== null && $ans !== '') {
					$given = trim((string) $request->getUserVar('cheesaCaptcha'));
					$session->unsetSessionVar('cheesaCaptchaAns'); // sekali pakai
					if ($given === '' || (string) $ans !== $given) {
						CheesaLoginThrottle::hit($ip);
						$session->setSessionVar('cheesaLoginMsg', 'Jawaban verifikasi keamanan salah. Silakan coba lagi.');
						return $this->index($args, $request);
					}
				}

				// 3) Catat percobaan.
				CheesaLoginThrottle::hit($ip);
			}
		} catch (\Throwable $e) {
			error_log('cheesaGuard signIn guard: ' . $e->getMessage());
		}
		// Serahkan ke login inti.
		return parent::signIn($args, $request);
	}

	/** Dipanggil LoginHandler inti ketika login berhasil -> bersihkan hitungan. */
	function _redirectAfterLogin($request) {
		require_once(__DIR__ . '/classes/LoginThrottle.inc.php');
		CheesaLoginThrottle::clear($request->getRemoteAddr());
		return parent::_redirectAfterLogin($request);
	}
}
