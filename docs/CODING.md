# コーディング規約
設計の方針 (どこに何を書くか) は [ARCHITECTURE.md](ARCHITECTURE.md) を参照。ここでは書き方を決める。

## 大きさ
- 1ファイル300行以内。超えそうなら先に分割する
- 1メソッド、1関数40行以内、ネストは3段以内を目安とする
- 基本的には1ファイル1責務。`Utils` や `Helper` に足すより、新しいクラスを作る
- 同じ意味、同じ変更理由の処理が複数箇所に出てきたら共通化を検討する

## 書き方
正直ここは本当に人によるので本人次第ではある (コーディング思想のぶつかり合い回避の余計な一言が、行数が多いと重要なロジックが埋もれるので画面でできるだけ多くのコードを一度に表示したいというのがある)

### 波括弧は同じ行に書く

```php
// GOOD
public function execute(string $accountId): void {
}

// BAD
public function execute(string $accountId): void
{
}
```

### 早期リターンを使い、1行で書けるなら1行にする

```php
// GOOD
if ($account === null) return null;

// BAD
if ($account === null) {
    return null;
}
```

```ts
// GOOD
if (!enabled) return null;
```

三項演算子は使ってよいが、入れ子にはしない。

### コンストラクタのプロパティ昇格は使わない
プロパティの宣言と代入を分けて書く (どのようなプロパティをクラス内で利用するか把握しやすくするため)

```php
// GOOD
class ClaimServiceAccount {
    private readonly AuthIdentityRepository $accounts;

    public function __construct(AuthIdentityRepository $accounts) {
        $this->accounts = $accounts;
    }
}

// BAD
class ClaimServiceAccount {
    public function __construct(private readonly AuthIdentityRepository $accounts) {}
}
```

### React のコンポーネントはアロー関数で書く
`function` 宣言ではなく、`const` にアロー関数を代入する。default export は定義のあとに書く。

```tsx
// GOOD
const SectionTitle = ({ children, note }: SectionTitleProps) => {
    return <Typography>{children}</Typography>;
};

export default SectionTitle;

// BAD
export default function SectionTitle({ children, note }: SectionTitleProps) {
    return <Typography>{children}</Typography>;
}
```

既存のコンポーネントは `function` 宣言で書かれている。触ったときに順次書き換える。

### 型を書く

- PHP: 引数・戻り値・プロパティに型を宣言する。配列の中身は PHPDoc で書く (`list<string>`、`array{id: string}` など)
- TypeScript: `any` を使わない

## コメント

- コメントには「なぜそうしたか」だけを書く。「何をしているか」はコードで分かるので基本的には書かない (コードを見てもわかりにくいなら書く場合もある)
- クラス、メソッド、関数には PHPDoc / TSDoc を書く
- 区切り線は短くする

```php
// GOOD
// 二度目以降は同じものを返す。呼び出し側が何度叩いても増えない

// BAD
// サービスアカウントを検索する
```

```php
// GOOD
// ---- コメント編集 ----

// BAD
// ─── コメント編集 ─────────────────────────

// ////////////////////////////////////////
// // コメント編集
```

## 例外
try/catch を書いてよいのは基本的には理由がない限りは次の場所だけ。それ以外では例外をそのまま上へ流す。

- 外部との境界 (ネットワーク、ファイル、DB、外部API)
- 入り口の最上位 (コントローラ、コマンド)
- 回復処理がある場所 (リトライ、フォールバック)

境界で捕まえたら、文脈を付けて投げ直す。握りつぶさない。

```php
// GOOD
try {
    return $this->client->send($request);
} catch (Throwable $e) {
    throw new FetchException("API fetch failed: {$url}", 0, $e);
}

// BAD: 呼び出し元が失敗を知れない
try {
    $this->process($data);
} catch (Exception $e) {
    Log::error($e);
}
```

例外の中で判定しなくてよいもの (早期リターンなど) は try の外に書く。

失敗の理由は1つの文言に潰さない。原因ごとに理由を返す。

## 表示する文字列

画面に出る文字列はコードに直接書かない。必ず翻訳キーを通す。仕組みは [翻訳](LANG.md) を参照。

| 場所 | 辞書 | 呼び出し |
| --- | --- | --- |
| PHP | `lang/server/<ロケール>.json` | `__('admin.backups.title')` |
| React | `lang/client/<ロケール>.json` | `t('...')` (`resources/js/lib/i18n.ts`) |

- 辞書を変えたら `php artisan lang:build` を実行する (キーの過不足を検証し、PHP 側の配列を生成する)
- キーは全ロケールに揃える
- ログにだけ出る例外の文言や、artisan コマンドの出力は対象外

## 設定値

環境変数や設定のキーに、古い名前へのフォールバックを書かない。

```php
// GOOD
config('chreeid.signing_key')

// BAD
env('CHREEID_SIGNING_KEY') ?? env('OLD_SIGNING_KEY')
```

## UI

- MUI の標準的な部品を使い、既存の画面 (ログイン画面など) と枠線、余白、ボタンの形を揃える
  - 枠線は `border: "1px solid", borderColor: "divider", borderRadius: 2`
- アニメーション (ホバー演出、パルスなど) や、角を強く丸めた独自ボタンは使わない
- カードは必要な場所だけに使う
- 作成、表示、更新、削除のうち、必要な操作は揃える

## 確認
できるだけコミット前に次を通す。

```bash
php artisan test --parallel # PHPのテスト
php vendor/bin/phpstan analyse # PHPの静的解析 (level 8、エラー0を保つ)
npm run typecheck # TypeScriptの型検査
php vendor/bin/pint # PHPの整形 (設定は pint.json。この規約に合わせてある)
```

PHPStanのエラーは、理由がない限り、`@phpstan-ignore` やキャストで黙らせず、原因の型を直す。

ビルド済みのフロントエンドのアセットはコミットしない (CIがビルドするため)
