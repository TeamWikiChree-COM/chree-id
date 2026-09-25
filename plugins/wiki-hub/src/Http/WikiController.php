<?php
namespace Plugins\WikiHub\Http;

use App\Modules\Plugin\Application\PluginContext;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Plugins\WikiHub\Application\ListWikis;

/**
 * 利用者が自分のウィキを横断して見る画面。
 */
class WikiController {
    private readonly PluginContext $context;
    private readonly ListWikis $wikis;

    public function __construct(PluginContext $context, ListWikis $wikis) {
        $this->context = $context;
        $this->wikis = $wikis;
    }

    /**
     * @return Response|RedirectResponse
     */
    public function index(): Response|RedirectResponse {
        $accountId = $this->context->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        return Inertia::render('wiki-hub::Index', ['groups' => $this->wikis->execute($accountId)]);
    }
}
