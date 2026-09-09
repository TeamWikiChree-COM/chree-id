<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 何も入れない。ChreeID のアカウントは本人の登録か、サービスからの
        // 遅延発行でしか作らない。素性の分からない行を用意すると、
        // それがどちらの経路で出来たものか追えなくなる
    }
}
