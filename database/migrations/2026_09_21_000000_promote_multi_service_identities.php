<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// 引き取り前のサービスアカウントが ChreeID に直接ログインし、そのまま別サービスへ OIDC で入ると、
// SelectServiceAccount が同じ認証主体に別サービスの行を作ってしまっていた。
// 複数サービスを束ねている時点で実質ユーザーアカウントなので、そのまま昇格させる。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        $ids = DB::table('service_accounts')
            ->select('auth_identity_id')
            ->groupBy('auth_identity_id')
            ->havingRaw('COUNT(DISTINCT client_id) > 1')
            ->whereNotIn('auth_identity_id', DB::table('user_accounts')->select('auth_identity_id'))
            ->pluck('auth_identity_id');

        $now = now();
        foreach ($ids as $id) {
            DB::table('user_accounts')->insert([
                'id' => Str::lower(Str::ulid()->toString()),
                'auth_identity_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * 昇格は戻さない。どの行がこのマイグレーションで作られたかを区別できないため。
     */
    public function down(): void {}
};
