# 翻訳 (lang)

画面やメールの文言は、コードに直接書かずに辞書から引く。ここでは辞書の置き場所と、言語が決まる仕組みを説明する。

## 辞書の置き場所

辞書は JSON で、サーバ用と画面用に分かれている。

| 辞書 | 使う場所 | 呼び出し |
| --- | --- | --- |
| `lang/server/<ロケール>.json` | PHP (バリデーションのメッセージ、メール、API のエラーなど) | `__('admin.backups.title')` |
| `lang/client/<ロケール>.json` | React の画面 | `t('...')` (`resources/js/lib/i18n.ts`) |
| `plugins/<名前>/lang/<ロケール>.json` | そのプラグインの画面 | `createTranslator()` で作った `t()` |

分けているのは、画面で使わない文言 (メール本文や API のエラー) まで利用者のブラウザに配らないため。プラグインの文言を本体の辞書に混ぜないのは、プラグインを足すたびに本体の `lang/` に差分が出ないようにするため。

キーはドットで区切る (`admin.backups.title`)。値に `:name` と書いた部分は、呼び出し時に差し込める。

```php
__('admin.backups.taken', ['name' => $name, 'tables' => 3, 'rows' => 120]);
```

```ts
t('admin.backups.count', { count: 3 });
```

## lang:build

```bash
php artisan lang:build
```

やっていることは2つ。

1. 検証: ロケールごとにキーの過不足が無いか、`:name` の差し込みがそろっているかを確かめる。欠けていれば失敗する
2. 生成: `lang/server/*.json` から、Laravel が読む PHP の配列を `generated/lang/` に書き出す

PHP に変換しているのは、OPcache に載せて毎回の JSON の読み込みを省くため。`generated/` は git に入れない。

### いつ実行するか

| 変えた辞書 | ローカル | CI |
| --- | --- | --- |
| `lang/client/` (画面) | 不要。Vite が JSON を直接読むので、`npm run dev` 中ならすぐ反映される | テストの前に検証される |
| `lang/server/` (PHP) | 必要。実行するまで PHP 側は古い文言のまま | テストとデプロイの前に自動で実行される |

CI では、テスト (`.github/workflows/test.yml`) とデプロイ (`.github/workflows/deploy.yml`、`tools/build_lang.php`) の前に自動で実行している。ローカルで忘れても本番に古い文言が出ることは無いが、キーの欠けは push するまで分からないので、辞書を変えたら一度は実行しておくとよい。

## 言語の決まり方

1つの要求ごとに、次の順で決める (`LocaleNegotiator`)。

1. 本人が選んだ言語。ログイン中は `auth_identities.locale`、未ログインは Cookie (`chreeid_locale`)
2. ブラウザの `Accept-Language`。`ja-JP` は `ja` として扱う
3. どれでも決まらなければ、`config/chreeid.php` の `locales` の先頭

画面側は、サーバが決めた言語を `<html lang>` から読む。

## 基準の言語に落とさない

翻訳が無いときに日本語 (基準の言語) で代わりに表示する、ということはしない。`lang:build` がキーの欠けを検出するので、片方にだけあるキーはそもそも存在しない。代わりの言語を用意すると、翻訳漏れが混ざった画面として気付かれずに出てしまう。

TypeScript でも、基準の辞書 (`lang/client/ja_jp.json`) のキーが型になっている。存在しないキーを `t()` に渡すと `npm run typecheck` で失敗する。

## 翻訳しないもの

- ログにだけ出る例外のメッセージ
- artisan コマンドの出力

## 言語を増やすとき

1. `lang/server/` と `lang/client/` に `<ロケール>.json` を足す (例: `ko_kr.json`)。キーは既存の辞書と同じにする
2. `config/chreeid.php` の `locales` に足す。並び順が切り替えメニューの並び順になる
3. `resources/js/lib/i18n.ts` の `CATALOGS` と `LABELS` に1行ずつ足す。言語名はその言語自身の表記にする (英語しか読めない人が「英語」を探せないため)
4. `php artisan lang:build` と `npm run typecheck` を通す
