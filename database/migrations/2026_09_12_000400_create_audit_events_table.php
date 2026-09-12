<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 監査ログ。
//
// **ログイン履歴もここに寄せる。** 直前に login_events を作ったが、
// 「誰が何をしたか」を2つの表に分けると、本人に見せる履歴と運営が追う記録が
// 食い違う。行は移してから落とす。
//
// 対象 (auth_identity_id) は退会で消える。個人の記録なので道連れでよい。
// 実行者 (actor_id) には外部キーを張らない。管理者のアカウントが消えても
// 「誰がやったか」の記録は残す必要がある。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // 何についての記録か。本人が見る一覧はこれで引く
            $table->foreignUlid('auth_identity_id')->nullable()->constrained('auth_identities')->cascadeOnDelete();

            // 誰がやったか。本人の操作なら対象と同じ、管理者なら管理者のID
            $table->ulid('actor_id')->nullable();

            // AuditAction の value が入る
            $table->string('action', 64);

            // 失敗も残す。ログインの失敗は本人が一番知りたい記録になる
            $table->boolean('succeeded')->default(true);

            // 行ごとに形の違う付随情報 (方式、連携先、消した件数など)
            $table->json('context')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auth_identity_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        if (!Schema::hasTable('login_events')) return;

        // 作ったばかりの表だが、既に記録が入っている環境がある
        foreach (DB::table('login_events')->orderBy('id')->cursor() as $row) {
            DB::table('audit_events')->insert([
                'id' => $row->id,
                'auth_identity_id' => $row->auth_identity_id,
                'actor_id' => $row->auth_identity_id,
                'action' => $row->succeeded ? 'login.succeeded' : 'login.failed',
                'succeeded' => $row->succeeded,
                'context' => json_encode(['method' => $row->method], JSON_THROW_ON_ERROR),
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'created_at' => $row->created_at,
            ]);
        }

        Schema::drop('login_events');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('audit_events');
    }
};
