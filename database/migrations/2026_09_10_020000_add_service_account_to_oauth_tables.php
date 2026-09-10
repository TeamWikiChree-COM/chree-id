<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 認可コードとアクセストークンに「どのサービスアカウントとして入ったか」を持たせる
//
// sub はサービスアカウントの持ち物なので、1人が同じサービスに複数持っていると
// 認証主体だけでは sub を決められない。認可のときに選んだものをここに控えて、
// トークン発行と userinfo で同じサービスアカウントを使う。
//
// nullable にしてあるのは、入れ替えの前に出したコードやトークンが生きているため。
// 空のときは従来どおり認証主体から引く (1つしか無ければ一意に決まる)。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('oauth_auth_codes', function (Blueprint $table): void {
            $table->foreignUlid('service_account_id')->nullable()
                ->constrained('service_accounts')->nullOnDelete();
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->foreignUlid('service_account_id')->nullable()
                ->constrained('service_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_account_id');
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_account_id');
        });
    }
};
