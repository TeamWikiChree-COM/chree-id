# プラグイン

ChreeID 本体に組み込まずに機能を足すための仕組み。`plugins/<name>/` に置くと自動で読み込まれる。

## 置き方

```
plugins/<name>/
  plugin.json                 名前・説明・ServiceProvider (必須)
  config.php                  設定 (任意)。あれば本体が config('<name>.…') に登録する
  src/                        名前空間 Plugins\<StudlyName>\  (例: wiki-hub → Plugins\WikiHub\)
    <StudlyName>ServiceProvider.php
  routes/web.php              ルート (任意)。あれば本体が web ミドルウェアと /plugins/<name> の接頭辞を付けて読む
  resources/lang/*.json       画面の文言 (ja_jp.json、en_us.json)。本体の resources/lang/ には混ぜない
  resources/js/Pages/*.tsx    画面。Inertia::render('<name>::<Page>') で出す
  tests/*Test.php             php artisan test で一緒に流れる
```

`src/` の中は、クラスが少ないうちは直下に平らに置く。本体のように Domain / Application / Infrastructure / Http に分けても、フォルダごとに1ファイルしか入らず辿る手間が増えるだけなので。クラスが増えて見通しが悪くなったら、そのとき本体と同じ層に分ける。

### plugin.json

```json
{
    "version": "0.1.0",
    "provider": "Plugins\\WikiHub\\WikiHubServiceProvider",
    "enabled": true,
    "title": { "ja": "Wiki Hub", "en": "Wiki Hub" },
    "description": { "ja": "…", "en": "…" }
}
```

`enabled` を `false` にすると読み込まない。

## 読み込みの流れ

```
bootstrap/providers.php                 Laravel が起動時に読むプロバイダの一覧
  └ PluginServiceProvider
      register()
        ├ new PluginRegistry(...)       plugins/ を読み、instance でコンテナに入れる
        ├ singleton(PluginMenu)         1つだけ作って使い回すよう登録する
        ├ mergeConfigFrom(config.php)   config.php があれば config('<name>.…') に登録する
        └ $app->register(<Name>ServiceProvider)
            └ <Name>ServiceProvider::register()   その場で呼ばれる

全プロバイダの register() が済んだあと、Laravel が各プロバイダの boot() を呼ぶ
  ├ PluginServiceProvider::boot()       routes/web.php があれば /plugins/<name> の下に読む
  └ <Name>ServiceProvider::boot(PluginMenu $menu)
```

### register() と boot()

| メソッド | 呼ばれる時点 | 書くもの |
| --- | --- | --- |
| `register()` | 登録された直後。ほかのプロバイダはまだそろっていない | コンテナへの登録 (`bind`、`singleton`) だけ。ほかのサービスは使わない |
| `boot()` | 全プロバイダの `register()` が済んだあと | メニューへの追加など、ほかのサービスを使う処理 |

### boot() に #[Override] を付けられない理由

親の `Illuminate\Support\ServiceProvider` には `register()` はあるが、`boot()` は宣言されていない。Laravel は `method_exists($provider, 'boot')` で探し、あれば呼ぶ。そのため `boot()` に `#[Override]` を付けると、上書きする親のメソッドが無いとして PHP がエラーにする。

親に宣言が無いのは、引数を自由に並べられるようにするため。宣言すると、子は親と同じ引数の形に縛られる。名前を打ち間違えてもエラーにならず、呼ばれないだけなので注意する。

### boot() の引数が自動で入る仕組み

Laravel は `boot()` をコンテナ経由 (`$app->call([$provider, 'boot'])`) で呼ぶ。呼ぶ前にリフレクションで引数の型を読み、その型名でコンテナから取り出して渡す。

- `PluginMenu`: `singleton` の登録に従い、最初に求められたときに作られる。全プラグインに同じものが渡るので、足した入口が1か所に集まる
- `PluginRegistry`: `PluginServiceProvider` が作って `instance` で入れたものがそのまま渡る。plugin.json の中身を見たいときに引数に足す

コンテナに登録していないクラスでも、コンストラクタの引数を同じ方法でたどって組み立てる。コントローラのコンストラクタに `PluginApi $api` と書くだけで入るのも同じ仕組み。

### 設定とルートの自動読み込み

`config.php` と `routes/web.php` は、置いておけば本体 (`PluginServiceProvider`) が読む。プラグイン側に読み込みの処理は要らない。

| ファイル | 本体がすること |
| --- | --- |
| `config.php` | `mergeConfigFrom` で `config('<name>.…')` に登録する。プラグインの `register()` より先に済ませるので、`register()` の中でも設定を読める |
| `routes/web.php` | `web` ミドルウェアと `/plugins/<name>` の接頭辞を付けて読む。URL をそろえるのは、本体の URL とぶつけないため |

```php
// routes/web.php。/plugins/wiki-hub になる
Route::get('/', [WikiController::class, 'index']);
```

本体に `config/<name>.php` を置くと、そちらの値が優先される。合わせるのは一番上の階層のキーだけで、たとえば `sources` を本体側に書くと `sources` は丸ごと置き換わる。`php artisan config:cache` している環境では、キャッシュを作った時点の値が使われる。

別の名前のファイルや、別のキー、別の URL にしたいときは、プラグインの ServiceProvider で自分で読む。

```php
public function register(): void {
    $this->mergeConfigFrom(__DIR__ . '/../settings.php', 'my-settings');
}

public function boot(): void {
    $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
}
```

## 本体とのつなぎ目

プラグインは本体と同じプロセスで動くので、本体のクラスも Laravel の機能も使える。
ただし本体の内部は変わることがある。

| 使うもの | 扱い |
| --- | --- |
| `App\Modules\Plugin\Application\PluginApi` | 本体を変えても互換性を保つ範囲。できるだけこれを使う |
| Laravel の機能 (ルート、ビュー、キャッシュ、HTTP クライアントなど) | 自由に使ってよい |
| 本体のそれ以外のクラス (モデル、Application など) | 使ってよいが、本体の変更で壊れることがある。壊れたらプラグイン側で直す |

プラグインが同じものを何度も必要とするようになったら、`PluginApi` に足す。

主な用途は次のとおり。

| 用途 | 使うもの |
| --- | --- |
| ログイン中のアカウント・運営かどうか・連携しているサービスアカウント | `App\Modules\Plugin\Application\PluginApi` |
| ダッシュボードや管理画面へ入口を足す | `App\Modules\Plugin\Domain\PluginMenu` の `addPlugin()` |
| 画面の部品 | `@/Components/…`、`@/lib/actions` など本体の部品をそのまま使ってよい |
| 画面の文言 | `createTranslator({ ja, en })` (`@/lib/i18n`) に自分の resources/lang/*.json を渡す |

### 入口を足す

```php
public function boot(PluginMenu $menu): void {
    $menu->addPlugin('wiki-hub', PluginMenu::AREA_DASHBOARD, $clientIds);
}
```

入口の名前と説明は plugin.json の `title` と `description`、URL は `/plugins/<name>` になる。読み込まれていないプラグインの名前を渡すと例外になるので、打ち間違えても黙って入口が消えることはない。

ダッシュボードの「利用可能なプラグイン」には、渡した client_id のどれかと連携している利用者にだけ出る (空なら全員)。

名前や URL を plugin.json と別にしたいときは、`PluginMenuItem` を自分で作って `add()` に渡す。

```php
$menu->add(new PluginMenuItem(PluginMenu::AREA_ADMIN, '/plugins/wiki-hub/settings', ['ja' => '設定'], [], []));
```

認証まわり (`AuthenticationPolicy`・OIDC のプロトコル部分) には手を出さないこと。

## デプロイ

`plugins/` はリポジトリに入れる。デプロイは差分を送るので、特別な手順は要らない。
クラスの読み込みは `PluginServiceProvider` が自前で行うので、composer の dump-autoload も要らない。
