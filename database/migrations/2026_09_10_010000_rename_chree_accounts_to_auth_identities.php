<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ChreeAccount を AuthIdentity に、service_account_links を service_accounts に改名する
//
// ChreeAccount という名前は「UserAccount / ServiceAccount とは別に、もう一つ
// アカウントがあるのか」という誤解を招いた。実体はアカウントではなく、
// 認証情報が属する認証主体なので AuthIdentity と呼ぶ (KAKUTEI.md)。
//
// service_account_links も、実体は既にサービスアカウントそのもの (id を持ち sub を持つ)。
// 「links」は対応表だった頃の名残なので落とす。
return new class extends Migration {
    /** 列名を chree_account_id から auth_identity_id へ変えるテーブル */
    private const TABLES_WITH_COLUMN = [
        'credentials',
        'one_time_tokens',
        'oauth_auth_codes',
        'oauth_access_tokens',
        'pending_registrations',
        'pending_email_changes',
        'service_accounts',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void {
        $this->renameTable('chree_accounts', 'auth_identities');
        $this->renameTable('chree_account_aliases', 'auth_identity_aliases');
        $this->renameTable('service_account_links', 'service_accounts');

        foreach (self::TABLES_WITH_COLUMN as $table) {
            $this->renameColumn($table, 'chree_account_id', 'auth_identity_id');
        }

        // UserAccount を行の有無で表す。origin は出自の記録に戻し、
        // 「束ねているか」の判定はこちらで行う (KAKUTEI.md)
        Schema::create('user_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // 1つの AuthIdentity に UserAccount は0..1
            $table->foreignUlid('auth_identity_id')->unique()
                ->constrained('auth_identities')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('user_accounts');

        foreach (self::TABLES_WITH_COLUMN as $table) {
            $this->renameColumn($table, 'auth_identity_id', 'chree_account_id');
        }

        $this->renameTable('service_accounts', 'service_account_links');
        $this->renameTable('auth_identity_aliases', 'chree_account_aliases');
        $this->renameTable('auth_identities', 'chree_accounts');
    }

    /**
     * 既に新しい名前になっている環境では何もしない。
     *
     * @param string $from 現在の名前
     * @param string $to 新しい名前
     * @return void
     */
    private function renameTable(string $from, string $to): void {
        if (!Schema::hasTable($from) || Schema::hasTable($to)) return;

        Schema::rename($from, $to);
    }

    /**
     * @param string $table 対象のテーブル
     * @param string $from 現在の列名
     * @param string $to 新しい列名
     * @return void
     */
    private function renameColumn(string $table, string $from, string $to): void {
        if (!Schema::hasTable($table)) return;
        if (!Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) return;

        Schema::table($table, function (Blueprint $blueprint) use ($from, $to): void {
            $blueprint->renameColumn($from, $to);
        });
    }
};
