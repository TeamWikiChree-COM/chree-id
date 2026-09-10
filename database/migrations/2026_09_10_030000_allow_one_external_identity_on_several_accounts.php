<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 同じ外部アカウント (Google 等) を複数の AuthIdentity に紐付けられるようにする
//
// 分離したとき、Google 連携は寄せ元にも新しい側にも置きたい (KAKUTEI.md)。
// 全アカウント横断の unique(type, identifier) があると両方には置けない。
//
// **パスキーの一意性は保つ。** パスキーのログインは credential_id から
// アカウントを引くので、同じ鍵が2つにあるとどちらに入るか決まらない。
// そのため `type <> 'oauth'` の部分ユニークに置き換える。
//
// 代わりに、同じ AuthIdentity 内で同じ外部アカウントが重複しないようにする。
return new class extends Migration {
    private const PARTIAL = 'credentials_type_identifier_unique_except_oauth';
    private const PER_IDENTITY = 'credentials_identity_type_identifier_unique';

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('credentials', function (Blueprint $table): void {
            $table->dropUnique(['type', 'identifier']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX ' . self::PARTIAL
            . " ON credentials (type, identifier) WHERE type <> 'oauth'",
        );

        DB::statement(
            'CREATE UNIQUE INDEX ' . self::PER_IDENTITY
            . ' ON credentials (auth_identity_id, type, identifier)',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        DB::statement('DROP INDEX IF EXISTS ' . self::PER_IDENTITY);
        DB::statement('DROP INDEX IF EXISTS ' . self::PARTIAL);

        Schema::table('credentials', function (Blueprint $table): void {
            $table->unique(['type', 'identifier']);
        });
    }
};
