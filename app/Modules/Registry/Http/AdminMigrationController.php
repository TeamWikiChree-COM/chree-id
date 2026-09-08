<?php
namespace App\Modules\Registry\Http;

use App\Modules\Registry\Application\DatabaseMigrations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * データベース構造の適用。
 *
 * 本番のデプロイはファイルを置くだけで artisan を走らせない。
 * 置いた直後は新しいコードと古い表が噛み合っていないので、
 * ここから適用して追いつかせる。
 */
class AdminMigrationController {
    public function __construct(private readonly DatabaseMigrations $migrations) {}

    /**
     * @return Response
     */
    public function index(): Response {
        return Inertia::render('Admin/Migrations/Index', [
            'pending' => $this->migrations->pending(),
            'applied' => array_reverse($this->migrations->applied()),
        ]);
    }

    /**
     * @return RedirectResponse
     */
    public function run(): RedirectResponse {
        // 未適用が無いのに走らせても困りはしないが、押し間違いは戻しておく
        if ($this->migrations->pending() === []) {
            return redirect('/admin/migrations');
        }

        $output = $this->migrations->run();

        return redirect('/admin/migrations')->with('migrationOutput', $output);
    }
}
