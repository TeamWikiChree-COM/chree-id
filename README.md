# ChreeID
ChreeIDはそれぞれのサービスに認証システムを持たないようにする認証基盤である。

これが必要である理由はバラバラな認証システムが各サービスに存在し、中には脆弱性のあるものも存在する。<br />
そのため、一つで管理することで認証機能だけをサービスから分離してセキュリティを維持する。

また、統合することでサービスをまたいだ連携を可能にする。<br />
例えば、ユーザーアカウントではパスキーなどサービス側が対応していないものだとしても使えるようなしくみだ。

ChreeIDは2つの側面がある
1. アカウントデータや認証システムをDokuFarmやModParksなどのサービスから切り離してChreeIDで管理するしくみ
2. デフォルトではサービスアカウントなのでこれは任意ではあるが、やりたい人は「サービスアカウント」を統合して「ユーザーアカウント」で管理できるようにする（この場合はChreeIDの存在を認識する）

各サービスのアカウント/認証を一元化し、ChreeIDをOpenID Providerとし、サービス側はRelying Partyになる。<br/>
既存サービスの利用体験は可能な限り維持する。
特徴としては存在を認識しなくても内部的にはChreeIDを利用するという点である。いわゆるサービスアカウントに関してはFirebase Authenticationに近いだろう。

詳しくは [アカウントモデル](docs/ACCOUNTS.md)。

## 技術構成

| 項目 | 内容 |
| --- | --- |
| バックエンド | PHP 8.5 / Laravel 13 |
| フロントエンド | Inertia + React + TypeScript + MUI |
| DB | PostgreSQL 18 |
| 認証 | OIDC (OpenID Provider)、パスワード、パスキー、TOTP、マジックリンク、Google / GitHub ログイン |

## すぐ動かす

```bash
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan chreeid:generate-key
php artisan migrate
npm run dev
```

[Todofile](https://github.com/Pitan76/Todofile) を入れていれば、`todo setup` と `todo front:dev` の2つで同じことができる。

データベースの作り方や、動かないときの対処は [セットアップ](docs/SETUP.md)。

## コードの場所
機能ごとに `app/Modules/<モジュール>/` に分かれている。各モジュールの中は `Domain` / `Application` / `Infrastructure` / `Http`。
画面は `resources/js/Pages/`、翻訳は `lang/`。

どこに何を書くかは [アーキテクチャ](docs/ARCHITECTURE.md)、書き方は [コーディング規約](docs/CODING.md)。

## ドキュメント

| 読みたいこと | 場所 |
| --- | --- |
| 開発環境の作り方 | [docs/SETUP.md](docs/SETUP.md) |
| 設計の方針 | [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) |
| 書き方の決まり | [docs/CODING.md](docs/CODING.md) |
| アカウントの仕組み | [docs/ACCOUNTS.md](docs/ACCOUNTS.md) |
| 用語リスト | [docs/term/README.md](docs/term/README.md) |
| 知らずに触ると事故になる決定 | [docs/DECISIONS.md](docs/DECISIONS.md) |
| データベースの構造 | [docs/DATABASE.md](docs/DATABASE.md) |
| 翻訳の仕組み | [docs/LANG.md](docs/LANG.md) |
| コードドキュメント (PHPDoc) | [teamwikichree-com.github.io/chree-id](https://teamwikichree-com.github.io/chree-id/) |
| API仕様 (OpenAPI) | [id.wikichree.com/api-docs/v1](https://id.wikichree.com/api-docs/v1) |
| サービス接続 | [docs/INTEGRATION.md](docs/INTEGRATION.md) |
| デプロイ | [docs/DEPLOY.md](docs/DEPLOY.md) |
| CI (GitHub Actions) | [docs/CI.md](docs/CI.md) |
| プラグイン | [docs/PLUGIN.md](docs/PLUGIN.md) |
