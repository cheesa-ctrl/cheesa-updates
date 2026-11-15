<?php

/**
 * @file plugins/generic/apcLoa/classes/ApcLoaGateway.inc.php
 *
 * @class ApcLoaGateway
 * @brief Midtrans Snap and Xendit Invoice integration.
 *
 * A payment is only accepted when ALL of these hold:
 *  - the notification is authentic (Midtrans signature / Xendit callback token),
 *  - the transaction is re-checked with the gateway API and is settled/paid,
 *  - the order id matches the one this plugin created for the record,
 *  - the paid amount is at least the invoice amount and the currency matches.
 */

class ApcLoaGateway {

	/** @return GuzzleHttp\Client */
	private static function http() {
		return Application::get()->getHttpClient();
	}

	private static function json($response) {
		$data = json_decode((string) $response->getBody(), true);
		return is_array($data) ? $data : [];
	}

	// ------------------------------------------------------------------
	// Midtrans Snap
	// ------------------------------------------------------------------

	private static function midtransBase($production, $api = false) {
		if ($api) return $production ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
		return $production ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
	}

	/**
	 * Create a Snap transaction.
	 * @return array ['ok' => bool, 'url' => string, 'error' => string]
	 */
	public static function midtransCreate($serverKey, $production, $orderId, $amount, $customer, $itemName, $finishUrl, $notifyUrl) {
		try {
			$response = self::http()->request('POST', self::midtransBase($production) . '/snap/v1/transactions', [
				'http_errors' => false,
				'timeout' => 25,
				'auth' => [$serverKey, ''],
				'headers' => [
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'X-Override-Notification' => $notifyUrl,
				],
				'body' => json_encode([
					'transaction_details' => ['order_id' => $orderId, 'gross_amount' => (int) round($amount)],
					'item_details' => [[
						'id' => substr($orderId, 0, 50),
						'price' => (int) round($amount),
						'quantity' => 1,
						'name' => mb_substr($itemName, 0, 50),
					]],
					'customer_details' => [
						'first_name' => mb_substr((string) $customer['name'], 0, 255),
						'email' => (string) $customer['email'],
					],
					'callbacks' => ['finish' => $finishUrl],
				]),
			]);
			$data = self::json($response);
			if (!empty($data['redirect_url'])) return ['ok' => true, 'url' => $data['redirect_url'], 'error' => ''];
			$error = isset($data['error_messages']) ? implode('; ', (array) $data['error_messages']) : ('HTTP ' . $response->getStatusCode());
			return ['ok' => false, 'url' => '', 'error' => $error];
		} catch (Exception $e) {
			return ['ok' => false, 'url' => '', 'error' => $e->getMessage()];
		}
	}

	/**
	 * Verify a Midtrans HTTP notification and re-check its status with the API.
	 * @return array|null ['order_id', 'paid' => bool, 'amount' => float, 'currency', 'ref']
	 */
	public static function midtransVerify(array $payload, $serverKey, $production) {
		foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $key) {
			if (!isset($payload[$key])) return null;
		}
		$expected = hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $serverKey);
		if (!hash_equals($expected, (string) $payload['signature_key'])) return null;

		// Do not trust the notification body: ask Midtrans for the current status
		try {
			$response = self::http()->request('GET', self::midtransBase($production, true) . '/v2/' . rawurlencode($payload['order_id']) . '/status', [
				'http_errors' => false,
				'timeout' => 25,
				'auth' => [$serverKey, ''],
				'headers' => ['Accept' => 'application/json'],
			]);
			$status = self::json($response);
		} catch (Exception $e) {
			return null;
		}
		if (($status['order_id'] ?? null) !== $payload['order_id']) return null;

		$tx = $status['transaction_status'] ?? '';
		$fraud = $status['fraud_status'] ?? 'accept';
		$paid = $tx === 'settlement' || ($tx === 'capture' && $fraud === 'accept');
		return [
			'order_id' => $status['order_id'],
			'paid' => $paid,
			'amount' => (float) ($status['gross_amount'] ?? 0),
			'currency' => strtoupper($status['currency'] ?? 'IDR'),
			'ref' => 'midtrans:' . ($status['transaction_id'] ?? '') . ':' . ($status['payment_type'] ?? ''),
		];
	}

	// ------------------------------------------------------------------
	// Xendit Invoice
	// ------------------------------------------------------------------

	/**
	 * Create a Xendit invoice.
	 * @return array ['ok' => bool, 'url' => string, 'id' => string, 'error' => string]
	 */
	public static function xenditCreate($secretKey, $externalId, $amount, $currency, $email, $description, $successUrl, $failureUrl) {
		try {
			$body = [
				'external_id' => $externalId,
				'amount' => $currency === 'IDR' ? (int) round($amount) : round((float) $amount, 2),
				'currency' => $currency,
				'description' => mb_substr($description, 0, 255),
				'success_redirect_url' => $successUrl,
				'failure_redirect_url' => $failureUrl,
			];
			if ($email) $body['payer_email'] = $email;
			$response = self::http()->request('POST', 'https://api.xendit.co/v2/invoices', [
				'http_errors' => false,
				'timeout' => 25,
				'auth' => [$secretKey, ''],
				'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
				'body' => json_encode($body),
			]);
			$data = self::json($response);
			if (!empty($data['invoice_url'])) return ['ok' => true, 'url' => $data['invoice_url'], 'id' => (string) ($data['id'] ?? ''), 'error' => ''];
			return ['ok' => false, 'url' => '', 'id' => '', 'error' => (string) ($data['message'] ?? ('HTTP ' . $response->getStatusCode()))];
		} catch (Exception $e) {
			return ['ok' => false, 'url' => '', 'id' => '', 'error' => $e->getMessage()];
		}
	}

	/**
	 * Verify a Xendit invoice callback and re-check the invoice with the API.
	 * @return array|null ['order_id', 'paid' => bool, 'amount' => float, 'currency', 'ref']
	 */
	public static function xenditVerify(array $payload, $callbackHeader, $callbackToken, $secretKey) {
		if ($callbackToken === '' || !hash_equals((string) $callbackToken, (string) $callbackHeader)) return null;
		if (empty($payload['id']) || empty($payload['external_id'])) return null;

		try {
			$response = self::http()->request('GET', 'https://api.xendit.co/v2/invoices/' . rawurlencode($payload['id']), [
				'http_errors' => false,
				'timeout' => 25,
				'auth' => [$secretKey, ''],
				'headers' => ['Accept' => 'application/json'],
			]);
			$invoice = self::json($response);
		} catch (Exception $e) {
			return null;
		}
		if (($invoice['external_id'] ?? null) !== $payload['external_id']) return null;

		$status = strtoupper($invoice['status'] ?? '');
		$amount = isset($invoice['paid_amount']) ? (float) $invoice['paid_amount'] : (float) ($invoice['amount'] ?? 0);
		return [
			'order_id' => $invoice['external_id'],
			'paid' => in_array($status, ['PAID', 'SETTLED'], true),
			'amount' => $amount,
			'currency' => strtoupper($invoice['currency'] ?? 'IDR'),
			'ref' => 'xendit:' . ($invoice['id'] ?? '') . ':' . ($invoice['payment_method'] ?? ''),
		];
	}
}
