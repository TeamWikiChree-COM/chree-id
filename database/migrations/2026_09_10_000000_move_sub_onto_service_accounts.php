<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// sub を service_account_links (サービスアカウント) 側へ移し、service_subject_ids を畳む
//
// sub はアカウントではなくサービスアカウントの持ち物にする (DEFINE-v2.md 3.2)。
// account_id は統合で書き換わるので、そちらに紐付けたままだと統合のたびに
// サービスから見た識別子が指す先を失う。
//
// これで「同じサービスに複数のサービスアカウントを持つ人」もそのまま統合できる。
// それぞれが自分の sub を持ったままで済むため。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('service_account_links', function (Blueprint $table): void {
            $table->string('sub', 64)->nullable()->after('service_user_id');

            // OIDC でログインしただけの人は、サービス側の識別子をまだ知らない
            $table->string('service_user_id', 190)->nullable()->change();
        });

        $this->backfill();

        Schema::table('service_account_links', function (Blueprint $table): void {
            $table->unique(['client_id', 'sub']);
        });

        Schema::dropIfExists('service_subject_ids');
    }

    /**
     * 既存の sub を移す。紐付けが無いものは、その sub のためのサービスアカウントを起こす。
     *
     * @return void
     */
    private function backfill(): void {
        foreach (DB::table('service_subject_ids')->get() as $subject) {
            $moved = DB::table('service_account_links')
                ->where('client_id', $subject->client_id)
                ->where('chree_account_id', $subject->chree_account_id)
                ->whereNull('sub')
                ->limit(1)
                ->update(['sub' => $subject->sub]);

            if ($moved > 0) continue;

            DB::table('service_account_links')->insert([
                'id' => Str::lower(Str::ulid()->toString()),
                'client_id' => $subject->client_id,
                'chree_account_id' => $subject->chree_account_id,
                'service_user_id' => null,
                'sub' => $subject->sub,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::create('service_subject_ids', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('client_id', 64);
            $table->foreignUlid('chree_account_id')->constrained('chree_accounts')->cascadeOnDelete();
            $table->string('sub', 64);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
            $table->unique(['client_id', 'chree_account_id']);
            $table->unique(['client_id', 'sub']);
        });

        Schema::table('service_account_links', function (Blueprint $table): void {
            $table->dropUnique(['client_id', 'sub']);
            $table->dropColumn('sub');
        });
    }
};
