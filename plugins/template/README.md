# Template

新しいプラグインを作るときのひな形。plugin.json で無効にしてあるので、このままでは読み込まれない。

作り方と仕組みは [プラグイン](../../docs/PLUGIN.md) を参照。コピーしてからの書き換え手順は、その「新しく作るとき」にある。

## 中身

| ファイル | 役目 |
| --- | --- |
| `plugin.json` | 名前、説明、ServiceProvider。`title` と `description` はダッシュボードの入口にも出る |
| `config.php` | 設定。`config('template.…')` で読める |
| `routes/web.php` | ルート。`/plugins/template` の下になる |
| `src/TemplateServiceProvider.php` | ダッシュボードに入口を足す |
| `src/TemplateController.php` | 画面を返す |
| `resources/js/Pages/Index.tsx` | 画面 |
| `resources/lang/*.json` | 画面の文言 |
| `tests/TemplateTest.php` | ひな形が動くことを確かめるテスト |

コピーしたら、この README は作ったプラグインの説明に書き換える。何をするプラグインか、画面の URL、必要な設定 (.env) を書いておくとよい。
