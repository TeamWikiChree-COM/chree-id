<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// origin を出自として固定していたため、引き取り・統合・昇格を経ても service のまま残っていた。
// 以後は UserAccounts::ensure が揃えるので、既にずれている分だけここで直す。
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        DB::table('auth_identities')
            ->where('origin', 'service')
            ->whereIn('id', DB::table('user_accounts')->select('auth_identity_id'))
            ->update(['origin' => 'user']);
    }

    /**
     * どの行が service だったかは残していないので戻さない。
     */
    public function down(): void {
    }
};
