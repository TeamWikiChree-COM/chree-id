# プラグイン

ChreeID 本体に組み込まずに機能を追加するための仕組み。`plugins/<name>/` に置くと自動で読み込まれる。

## 置き方

```
plugins/<name>/
  plugin.json                 名前、説明、ServiceProvider (必須)
  config.php                  設定 (任意)。あれば本体が config('<name>.…') に登録する
  src/                        名前空間 Plugins\<StudlyName>\  (例: wiki-hub → Plugins\WikiHub\)
    <StudlyName>ServiceProvider.php
  routes/web.php              ルート (任意)。あれば本体が web ミドルウェアと /plugins/<name> の接頭辞を付けて読む
  resources/lang/*.json       画面の文言 (ja_jp.json、en_us.json)。本体の resources/lang/ には混ぜない
  resources/js/Pages/*.tsx    画面。Inertia::render('<name>::<Page>') で出す
  resources/js/types.ts       サーバから渡る値の型 (任意)。props の型は使う画面、部品のファイルに書く
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

管理画面の「プラグイン」(`/admin/plugins`) からも切り替えられる。画面は plugin.json の `enabled` だけを書き換える。
本番の plugin.json を画面で切り替えたあと、リポジトリ側の plugin.json を変えてデプロイすると、画面での切り替えは上書きされる。リポジトリの `enabled` も合わせておく。

### 新しく作るとき
ひな形として `plugins/template/` を用意している。仕組みの説明をコメントに書いてあるので、読みながら書き換える。

1. `plugins/template/` をコピーし、フォルダ名をプラグイン名 (kebab-case) にする
2. plugin.json の `provider`、`title`、`description` を書き換え、`enabled` を `true` にする
3. `src/` と `tests/` の名前空間 `Plugins\Template` を `Plugins\<StudlyName>` に、クラス名の `Template` をプラグイン名にする
4. `'template::Index'`、`config('template.…')`、`addPlugin('template', …)` の `template` をフォルダ名にする

ひな形は plugin.json で無効にしてあるので、本番では読み込まれない。ひな形自身のテスト (`plugins/template/tests/`) だけが、テストの中で読み込んで動くことを確かめている。本体の変更でひな形が動かなくなると、このテストが落ちる。

本体の読み込み処理そのもののテストには、別に `tests/Fixtures/plugins/example/` を使う。実在のプラグインやひな形の都合に左右されないようにするため。

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

| メソッド | 呼び出される時点 | 記述する内容 |
| --- | --- | --- |
| `register()` | 登録された直後。ほかのプロバイダはまだそろっていない | コンテナへの登録 (`bind`、`singleton`) だけ。ほかのサービスは使わない |
| `boot()` | 全プロバイダの `register()` が済んだあと | メニューへの追加など、ほかのサービスを使う処理 |

### boot() に #[Override] を付けられない理由

親の `Illuminate\Support\ServiceProvider` には `register()` はあるが、`boot()` は宣言されていない。Laravel は `method_exists($provider, 'boot')` で探し、あれば呼ぶ。そのため `boot()` に `#[Override]` を付けると、上書きする親のメソッドが無いとして PHP がエラーにする。

親に宣言が無いのは、引数を自由に並べられるようにするため。宣言すると、子は親と同じ引数の形に縛られる。名前を打ち間違えてもエラーにならず、呼ばれないだけなので注意する。

### boot() の引数が自動で入る仕組み

Laravel は `boot()` をコンテナ経由 (`$app->call([$provider, 'boot'])`) で呼ぶ。呼ぶ前にリフレクションで引数の型を読み、その型名でコンテナから取り出して渡す。

- `PluginMenu`: `singleton` の登録に従い、最初に求められたときに作られる。全プラグインに同じものが渡るので、追加した入口が1か所に集まる
- `PluginRegistry`: `PluginServiceProvider` が作って `instance` で入れたものがそのまま渡る。plugin.json の中身を見たいときに引数に追加する

コンテナに登録していないクラスでも、コンストラクタの引数を同じ方法でたどって組み立てる。コントローラのコンストラクタに `PluginApi $api` と書くだけで入るのも同じ仕組み。

### 設定とルートの自動読み込み

`config.php` と `routes/web.php` は、置いておけば本体 (`PluginServiceProvider`) が読む。プラグイン側に読み込みの処理は要らない。

| ファイル | 本体の処理 |
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

| 対象 | 互換性 |
| --- | --- |
| `App\Modules\Plugin\Application\PluginApi` | 本体を変えても互換性を保つ範囲。できるだけこれを使う |
| `App\Modules\*\Facades\*Registry` (`ExternalIdpRegistry`、`ScopeRegistry`、`CredentialRegistry`) | 同上。起動時に本体の一覧へ自分を追加するためのもの |
| Laravel の機能 (ルート、ビュー、キャッシュ、HTTP クライアントなど) | 自由に使ってよい |
| 本体のそれ以外のクラス (モデル、Application など) | 使ってよいが、本体の変更で壊れることがある。壊れたらプラグイン側で直す |

プラグインが同じものを何度も必要とするようになったら、`PluginApi` に追加する。
本体の一覧へ追加する口が足りないときは、その一覧の Facade を作る。

主な用途は次のとおり。

| 用途 | 対象 |
| --- | --- |
| ログイン中のアカウント、運営かどうか、連携しているサービスアカウント | `App\Modules\Plugin\Application\PluginApi` |
| ダッシュボードや管理画面へ入口を追加する | `App\Modules\Plugin\Domain\PluginMenu` の `addPlugin()` |
| ログイン画面に外部 IdP を追加する | `ExternalIdpRegistry::register()` (Facade) と `PluginApi` の `finishExternalLogin()` |
| OIDC 以外の方式でサービスにログインさせる | `PluginApi` の `authorizeService()` と `takeServiceSignIn()` |
| OIDC の scope とクレームを追加する | `ScopeRegistry::register()` (Facade)。サービスに scope を許しておく |
| 認証方式の検証のしかたを追加する | `CredentialRegistry::register()` (Facade)。方式の種類 (`CredentialType`) は本体の列挙型なので、新しい種類には本体の変更も要る。認証が成り立ったかの判断 (`AuthenticationPolicy`) は変えられない |
| 画面の部品 | `@/Components/…`、`@/lib/actions` など本体の部品をそのまま使ってよい |
| 画面の文言 | `createTranslator({ ja, en })` (`@/lib/i18n`) に自分の resources/lang/*.json を渡す |

### 入口を追加する

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

### 外部 IdP を追加する

ログイン画面に外部 IdP を追加する。例は `plugins/google/` (OAuth / OIDC) と `plugins/saml/` (SAML)。

本体は IdP の名前を知らず、共通の型だけを持つ。IdP ごとの中身はプラグインが持つ。

| 場所 | 責務 |
| --- | --- |
| 本体 | 送り出しと認可コードの受け口 (`/auth/<name>/redirect`、`/auth/<name>/callback`)、戻ってきたあとの共通の処理 (state の照合、アカウントの紐付け、停止の確認、セッション、戻り先への移動) |
| プラグイン | 送り先の URL、コードの交換、返ってきた情報の確かめ方、ボタンの名前とアイコン |

```php
use App\Modules\ExternalLogin\Facades\ExternalIdpRegistry;

public function boot(): void {
    ExternalIdpRegistry::register($this->app->make(MyIdp::class));
}
```

`MyIdp` は `App\Modules\ExternalLogin\Domain\ExternalIdp` を実装する。追加すると、ログイン画面と連携の設定画面にボタンが出る。
ボタンの名前とアイコンは `display()` で返す (`ExternalIdpDisplay`)。フロントの一覧に追加する必要は無い。
送り出しは本体の `/auth/<name>/redirect` が受け持ち、`authorizationUrl($state, $nonce)` の URL へ利用者を送る。

認可コードで戻る IdP (OAuth / OIDC) は、`CodeExchangeIdp` を実装する。戻り先は本体の `/auth/<name>/callback` がそのまま受け、`exchange($code, $nonce)` を呼ぶ。プラグインにルートは要らない。

それ以外の形で戻る IdP (SAML など) は、プラグインのルートで受けて `PluginApi::finishExternalLogin()` に渡す。

```php
return $api->finishExternalLogin('my-idp', $state, fn (string $nonce): ExternalIdentity => $this->verify($response, $nonce));
```

state の照合、アカウントの紐付け、停止の確認、セッションは本体が受け持つ。プラグインが書くのは、応答を確かめて `ExternalIdentity` を返すところだけ。確かめられなければ `RuntimeException` を投げる。

`ExternalIdentity` の `emailVerified` を true にすると、同じメールの既存アカウントへ自動で紐付く。IdP がメールの持ち主を確かめていないときは false にする。

### OIDC 以外の方式でサービスにログインさせる

ChreeID が IdP として、SAML などの方式でサービスにログインさせたいときに使う。例は `plugins/saml/` の `Idp/`。
サービスは本体の `oauth_clients` に登録したものを使い、方式ごとの設定だけをプラグインが持つ。

```php
// サービスからの要求を確かめたあと
return $api->authorizeService($clientId, ['openid', 'email'], url('/plugins/my-plugin/resume'));

// 戻り先 (/plugins/my-plugin/resume?grant=…)
$signIn = $api->takeServiceSignIn($request->string('grant')->toString());
```

ログイン、引き取り前の確認、同意、サービスアカウントの選択は、OIDC の /oauth/authorize と同じ手順で本体が進める。
`takeServiceSignIn()` は sub と、スコープの範囲の属性を返す。どちらも OIDC で渡す値と同じなので、同じサービスアカウントなら方式が違っても同じ人として扱える。

戻り先は `/plugins/` の下に限る。引換券 (grant) は一度しか使えず、始めたのと同じブラウザでしか使えない。

認証まわりのそれ以外 (`AuthenticationPolicy`、OIDC Provider のプロトコル部分) には手を出さないこと。

## プラグインのインデックスとキャッシュ
plugins/ を探して plugin.json を読んだ結果は、`bootstrap/cache/plugins.php` に控えておき、次のリクエストからはそれを読む。探して読む処理は毎リクエストかかり、プラグインが増えるほど重くなるため。

| 再生成タイミング | 判定方法 |
| --- | --- |
| プラグインのフォルダが増えた、または減った | plugins/ の中のフォルダ名の一覧を控えと比べる |
| plugin.json を書き換えた | plugin.json の更新時刻を控えと比べる |
| 管理画面で有効、無効を切り替えた | 切り替えたときに控えを消す |

フォルダの増減を plugins/ 自身の更新時刻で見ないのは、ファイルシステムによっては (exFAT など) 変わらないため。
更新時刻は秒単位なので、同じ秒のうちの書き換えは見逃すことがある。本体が書き換えるときは控えを消すので、問題になるのは手で書き換えたときだけ。控えは消してもよく、次のリクエストで作り直される。

## デプロイ
`plugins/` はリポジトリに入れる。デプロイは差分を送るので、特別な手順は要らない。
クラスの読み込みは `PluginServiceProvider` が自前で行うので、composer の dump-autoload も要らない。

### 別のリポジトリにあるプラグイン

プラグインは git のサブモジュールとして `plugins/<name>/` に置いてもよい。読み込み方は同じ。

| プラグイン | リポジトリ |
| --- | --- |
| wiki-hub | [TeamWikiChree-COM/chreeid-wiki-hub](https://github.com/TeamWikiChree-COM/chreeid-wiki-hub) |

```bash
# すでに clone してある場合は、サブモジュールの中身を取ってくる
git submodule update --init

# プラグインの新しいコミットを取り込む
git -C plugins/wiki-hub pull
git add plugins/wiki-hub
git commit
```

chree-id が記録しているのは、サブモジュールのどのコミットを使うかだけ。プラグインのリポジトリに push しても、chree-id で上のように取り込んでコミットするまで本番には出ない。
デプロイ (`tools/deploy.py`) は、サブモジュールの中の差分も送る。
