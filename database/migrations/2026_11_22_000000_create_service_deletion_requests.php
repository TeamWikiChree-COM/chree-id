<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 退会時に、連携サービスへアカウントの削除を依頼するための列とテーブル。
//
// 依頼は auth_identities に FK を張らない。物理削除の cascade で service_accounts が消えても、
// 送り終えるまで sub を持ち続ける必要があるため。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            // 削除依頼の送り先。null なら削除依頼に対応していない
            $table->string('deletion_request_uri', 500)->nullable();
        });

        Schema::create('service_deletion_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // サービスが消えたら依頼も送れないので一緒に消す
            $table->string('client_id', 64);
            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();

            $table->string('sub')->nullable();
            $table->string('service_user_id')->nullable();

            // 拒否されたときの通知先。退会した本人の行は消えるので写しておく
            $table->string('notify_email')->nullable();

            // ServiceDeletionStatus の value
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('reason', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('service_deletion_requests');

        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('deletion_request_uri');
        });
    }
};
