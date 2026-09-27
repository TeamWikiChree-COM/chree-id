# Yahoo! JAPAN ログイン

Yahoo! JAPAN ID で ChreeID にログインできるようにする。ID 連携 (YConnect v2) を使う。
ログイン画面と連携の設定画面に、Google や GitHub と同じようにボタンが出る。

## 使い始める

1. Yahoo! JAPAN デベロッパーネットワークでアプリケーションを登録する
   - アプリケーションの種類はサーバーサイド
   - コールバック URL は `<ChreeID の URL>/auth/yahoo-japan/callback`
   - 使う属性は、ID 連携の基本情報とメールアドレス
2. 受け取った Client ID とシークレットを ChreeID の .env に書く

```
YAHOO_JAPAN_CLIENT_ID=
YAHOO_JAPAN_CLIENT_SECRET=
```

3. 管理画面の「プラグイン」で有効にする

2つが揃うまでボタンは出ない。

## 受け取るもの

| 項目 | 取り方 |
| --- | --- |
| 外部アカウントの ID | id_token の sub |
| メール | UserInfo API。id_token には載らない |
| メールが確かめ済みか | UserInfo の email_verified。true のときだけ、同じメールの既存アカウントへ自動で紐付く |
| 名前 | UserInfo の name (無ければ nickname) |

UserInfo の sub が id_token と違うときは受けない。
