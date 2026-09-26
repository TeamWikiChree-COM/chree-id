# プラグイン

ChreeID 本体に組み込まずに機能を足すための仕組み。`plugins/<name>/` に置くと自動で読み込まれる。

## 置き方

```
plugins/<name>/
  plugin.json                 名前・説明・ServiceProvider (必須)
  config.php                  設定 (任意。ServiceProvider で mergeConfigFrom する)
  src/                        名前空間 Plugins\<StudlyName>\  (例: wiki-hub → Plugins\WikiHub\)
    <StudlyName>ServiceProvider.php
  routes/web.php              URL は /plugins/<name>/… にそろえる
  lang/ja_jp.json, en_us.json 画面の文言。本体の lang/ には混ぜない
  resources/js/Pages/*.tsx    画面。Inertia::render('<name>::<Page>') で出す
  tests/*Test.php             php artisan test で一緒に流れる
```

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

## 本体とのつなぎ目

プラグインから触ってよいのは次のものだけ。 本体のモデルやリポジトリを直接使うと、
本体の内部を変えるたびにプラグインが壊れる。

| 用途 | 使うもの |
| --- | --- |
| ログイン中のアカウント・運営かどうか・連携しているサービスアカウント | `App\Modules\Plugin\Application\PluginApi` (読み取りのみ) |
| ダッシュボードや管理画面へ入口を足す | `App\Modules\Plugin\Domain\PluginMenu` に `PluginMenuItem` を登録 |
| 画面の部品 | `@/Components/…`、`@/lib/actions` など本体の部品をそのまま使ってよい |
| 画面の文言 | `createTranslator({ ja, en })` (`@/lib/i18n`) に自分の lang/*.json を渡す |

ダッシュボードの「利用可能なプラグイン」には、`PluginMenuItem` に渡した client_id の
どれかと連携している利用者にだけ出る (空なら全員)。

認証まわり (`AuthenticationPolicy`・OIDC のプロトコル部分) には手を出さないこと。

## デプロイ

`plugins/` はリポジトリに入れる。デプロイは差分を送るので、特別な手順は要らない。
クラスの読み込みは `PluginServiceProvider` が自前で行うので、composer の dump-autoload も要らない。
