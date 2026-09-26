# アーキテクチャ設計
ChreeID のアーキテクチャ設計はモジュール分割、軽いレイヤードとする。学習コストなどの懸念からクリーンアーキテクチャは採らない。

## なぜクリーンアーキテクチャにしないか
- 開発者は多くても3人程度見込み。Laravelの素直な書き方にモジュール分割を足しただけの方が覚えることが少ない
- Laravelを前提にしており、DBやフレームワークを差し替える予定がない
    - 差し替えのための抽象化は学習面でも実装面でもコストだけが増えると考えられる
- テストはDB込みのFeatureテストで全件50秒ほど
    - テストのために層を切り離す必要がない
- Entityごとに Repository、Interface、Mapperを置くとファイル数が倍になり、1ファイル300行、1メソッド40行に収める方針と合わせると、読むファイルがさらに増える

## ディレクトリ
```
app/Modules/<モジュール>/
├── Domain/          列挙型、値オブジェクト、DBに依存しないルール、差し替え口のインターフェース
├── Application/     ユースケース。1クラス1操作 (例: IssueServiceAccount)
├── Infrastructure/  Eloquent モデル、外部 API、ファイル、暗号などの I/O
└── Http/            コントローラ、ミドルウェア
```

| モジュール | 担当 |
| --- | --- |
| `Admin` | 管理画面と運用 (アカウントの管理、問題の検出、マイグレーション、ログ、掃除) |
| `ApiDocs` | API の OpenAPI ドキュメント |
| `Audit` | 監査ログ (ログイン履歴、管理操作の記録) |
| `Backup` | データベースの暗号化バックアップ (サーバ内、Google Drive) |
| `Client` | 接続するサービス (クライアント) の登録、認証、信頼状態、サーバ間 API の入口 |
| `Credential` | 認証手段 (パスワード、マジックリンク、TOTP、パスキー、復旧コード) と認証の成立判定 |
| `Device` | ログイン中のセッションと、信頼した端末 |
| `ExternalLogin` | 外部IdP (Google、GitHub) でのログインと連携 |
| `Identity` | アカウント本体 (登録、メールアドレス、表示名、アイコン、退会) |
| `Linking` | サービスアカウントの発行、引き取り、統合 |
| `Plugin` | プラグインの読み込みとメニュー |
| `Provider` | OIDC プロバイダ (認可、トークン発行、ID Token、claims) |

## 依存の向き

```
Http ──→ Application ──→ Domain
              │
              └──→ Infrastructure (Eloquent / 外部 I/O)
```

## ルール

### 1. Application から Eloquent を直接使ってよい

Repository を挟まずにクエリを書いてよい。

### 2. Http は「入力の検証・Application の呼び出し・レスポンスの組み立て」だけ

クエリや業務判断をコントローラに書かない。画面に渡す形への整形が大きくなったら Presenter に分ける (例: [ClientPresenter](../app/Modules/Client/Http/ClientPresenter.php))。

### 3. Domain は DB に依存しない

Domain に置くのは列挙型・値オブジェクト・ルール・差し替え口のインターフェースだけ。Eloquent モデルや Infrastructure のクラスを参照しない。

### 4. Repository は必要になったときだけ作る

作るのは次のどちらかのときに限る。

- 同じクエリが2か所以上に出てくる
- 「数える条件」と「消す条件」のように、食い違うと困る条件を1か所で共有させたい (例: [PruneTokens](../app/Modules/Admin/Application/PruneTokens.php) / [PurgeDeletedAccounts](../app/Modules/Identity/Application/PurgeDeletedAccounts.php))

### 5. モジュールをまたぐときは相手の Application を通す

他のモジュールの Eloquent モデルやテーブルを直接触らない。触ると変更の影響範囲が追えなくなる。

### 6. インターフェースは差し替え口にだけ置く

実際に実装が複数あるところだけに置く。新しい実装は、クラスを1つ書いてレジストリに1行登録するだけで足せる形を保つ。

#### レジストリ
差し替え口ごとに、実装を束ねておく入れ物を1つ置いている。これをレジストリと呼ぶ (名前は `〜Registry`)。各モジュールの `Domain/` に置いている。

- レジストリは [Registry](../app/Support/Registry/Registry.php) を継承する。登録、二重登録の検出、取り出しを定義する。
- 起動時に ServiceProvider で実装を登録し、アプリ全体で1つだけ持つ (singleton)
- 使う側はレジストリから実装を引く。具体的な実装クラスを直接知らない
- 同じキーを二重に登録すると起動時に例外で止まる。黙って上書きされると、認証方式が意図せず差し替わっても気付けないため

| 差し替え口 | インターフェース | レジストリ | 登録する場所 |
| --- | --- | --- | --- |
| 認証方式 (パスワード、TOTP、パスキーなど) | [CredentialVerifier](../app/Modules/Credential/Domain/Verifier/CredentialVerifier.php) | [CredentialRegistry](../app/Modules/Credential/Domain/CredentialRegistry.php) | [CredentialServiceProvider](../app/Providers/CredentialServiceProvider.php) |
| 外部IdP (Google、GitHub) | [ExternalIdp](../app/Modules/ExternalLogin/Domain/ExternalIdp.php) | [ExternalIdpRegistry](../app/Modules/ExternalLogin/Domain/ExternalIdpRegistry.php) | [ExternalLoginServiceProvider](../app/Providers/ExternalLoginServiceProvider.php) |
| OIDC の scope | [ClaimsResolver](../app/Modules/Provider/Domain/Claims/ClaimsResolver.php) | [ScopeRegistry](../app/Modules/Provider/Domain/Claims/ScopeRegistry.php) | [OidcServiceProvider](../app/Providers/OidcServiceProvider.php) |
| プラグイン | `plugins/<名前>/plugin.json` | なし | 置けば自動で読み込まれる ([プラグイン](PLUGIN.md)) |

たとえば外部IdP を足すときは、`ExternalIdp` を実装したクラスを書き、`ExternalLoginServiceProvider` に1行足す。

```php
$this->app->singleton(ExternalIdpRegistry::class, function (): ExternalIdpRegistry {
    $registry = new ExternalIdpRegistry();

    $registry->register($this->app->make(GoogleIdp::class));
    $registry->register($this->app->make(GitHubIdp::class));
    $registry->register($this->app->make(DiscordIdp::class)); // 足すのはこの1行

    return $registry;
});
```

次の2つは差し替え口にしない。

- OIDC のプロトコル部分: 仕様が RFC で決まっているので、変える余地が無い
- 認証成立の判定 ([AuthenticationPolicy](../app/Modules/Credential/Domain/AuthenticationPolicy.php)): すべての認証経路がここを通る。拡張できるようにすると、抜け道を作れてしまう

## 現状とのずれ (2026-09-26 時点)

| ルール | 状態 |
| --- | --- |
| 2. Http から Eloquent を直接使っている | 15 ファイル (管理画面に多い) |
| 3. Domain から Infrastructure を参照している | 4 ファイル |
| 5. モジュールをまたいだ直接参照 | 未調査 |

新しく書くコードはルールに従い、既存のずれはリファクタリングで順に解消する。
