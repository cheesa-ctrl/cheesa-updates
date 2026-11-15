<?php

/**
 * @file plugins/generic/apcLoa/classes/ApcLoaMigration.inc.php
 *
 * @class ApcLoaMigration
 * @brief Creates the table that stores one LoA/APC record per accepted submission.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class ApcLoaMigration extends Migration {

	const TABLE = 'apc_loa_records';

	public function up() {
		if (Capsule::schema()->hasTable(self::TABLE)) return;
		Capsule::schema()->create(self::TABLE, function (Blueprint $table) {
			$table->bigIncrements('record_id');
			$table->bigInteger('context_id');
			$table->bigInteger('submission_id');
			$table->string('status', 20)->default('unpaid'); // nofee | unpaid | pending | paid | waived | revoked
			$table->decimal('amount', 16, 2)->default(0);
			$table->string('currency', 3)->default('IDR');
			$table->integer('seq')->default(0);
			$table->integer('seq_year')->default(0);
			$table->string('loa_number', 120)->nullable();
			$table->string('invoice_number', 120)->nullable();
			$table->string('receipt_number', 120)->nullable();
			$table->string('token', 64);
			$table->dateTime('accepted_at')->nullable();
			$table->dateTime('due_at')->nullable();
			$table->dateTime('paid_at')->nullable();
			$table->string('method', 20)->nullable();         // bank | midtrans | xendit | manual
			$table->string('gateway_order', 64)->nullable();
			$table->string('gateway_ref', 128)->nullable();
			$table->string('proof_path', 255)->nullable();
			$table->string('proof_name', 255)->nullable();
			$table->text('proof_note')->nullable();
			$table->dateTime('proof_at')->nullable();
			$table->text('editor_note')->nullable();
			$table->dateTime('loa_sent_at')->nullable();
			$table->dateTime('updated_at')->nullable();
			$table->unique(['context_id', 'submission_id'], 'apc_loa_ctx_sub');
			$table->unique('token', 'apc_loa_token');
			$table->index(['context_id', 'status'], 'apc_loa_ctx_status');
		});
	}

	public function down() {
		Capsule::schema()->dropIfExists(self::TABLE);
	}
}
