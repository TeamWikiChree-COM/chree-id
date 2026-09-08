<?php
namespace App\Modules\Registry\Application;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;

/**
 * データベースの構造を最新に合わせる。
 *
 * 本番は共用サーバでコマンドを叩きにくい。デプロイもファイルを置くだけで
 * artisan は走らないので、置いた直後の本番は「新しいコードと古い表」になる。
 * その差を管理画面から埋められるようにするための裏方。
 *
 * できるのは前に進めることだけ。巻き戻しも作り直しも用意しない。
 * 押し間違いで消える種類の操作を、画面に置いておく理由がない。
 */
class DatabaseMigrations {
    public function __construct(private readonly Migrator $migrator) {}

    /**
     * まだ適用されていないマイグレーション。
     *
     * @return list<string>
     */
    public function pending(): array {
        $ran = $this->applied();

        return array_values(array_filter(
            $this->all(),
            fn (string $name): bool => !in_array($name, $ran, true),
        ));
    }

    /**
     * 適用済みのマイグレーション。
     *
     * @return list<string>
     */
    public function applied(): array {
        // 表そのものが無い環境では、当然まだ何も適用されていない
        if (!$this->migrator->repositoryExists()) return [];

        return array_values($this->migrator->getRepository()->getRan());
    }

    /**
     * 未適用のぶんを適用する。
     *
     * @return string artisan の出力。何が走ったかを画面に出すために返す
     */
    public function run(): string {
        Artisan::call('migrate', ['--force' => true]);

        return trim(Artisan::output());
    }

    /**
     * ファイルとして置かれている全マイグレーション。
     *
     * @return list<string>
     */
    private function all(): array {
        $paths = array_merge($this->migrator->paths(), [database_path('migrations')]);

        return array_keys($this->migrator->getMigrationFiles($paths));
    }
}
