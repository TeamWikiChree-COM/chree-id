<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 検証専用。管理画面から本当に適用されたかを、表が出来たかどうかで確かめる。
// 本番の migrations には置かず、テストが自分でこのパスを登録したときだけ見える
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('migration_probe', function (Blueprint $table): void {
            $table->id();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('migration_probe');
    }
};
