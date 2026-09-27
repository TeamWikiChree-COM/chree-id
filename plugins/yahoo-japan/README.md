# Yahoo! JAPAN ログイン

Yahoo! JAPAN ID で ChreeID にログインできるようにする。ID 連携 (YConnect v2) を使う。
ログイン画面と連携の設定画面に、Google や GitHub と同じようにボタンが出る。

## 使い始める

1. Yahoo! JAPAN デベロッパーネットワークでアプリケーションを登録する
   - ID連携は「利用する」にする。「利用しない」だとログインに使えない
   - アプリケーションの種類はクライアントサイドでもサーバーサイドでもよい
   - コールバック URL は `<ChreeID の URL>/auth/yahoo-japan/callback`
2. 受け取った Client ID を ChreeID の .env に書く

```
YAHOO_JAPAN_CLIENT_ID=
YAHOO_JAPAN_CLIENT_SECRET=     サーバーサイドで登録したときだけ。クライアントサイドなら空
YAHOO_JAPAN_USERINFO=false     属性取得 API の審査に通ったときだけ true
```

3. 管理画面の「プラグイン」で有効にする

Client ID が入るまでボタンは出ない。
シークレットの有無にかかわらず PKCE を付ける。シークレットがあれば Basic 認証も付ける。

## メールと名前

メールと名前は属性取得 API (UserInfo) からしか取れない。この API は審査に通ったアプリでしか使えず、個人の登録ではそもそも使えない。
そのため既定 (`YAHOO_JAPAN_USERINFO=false`) では UserInfo を呼ばず、スコープも openid だけを求める。

| 項目 | UserInfo を使わない (既定) | UserInfo を使う |
| --- | --- | --- |
| 外部アカウントの ID | id_token の sub | id_token の sub |
| メール | 無し | UserInfo の email |
| 名前 | 無し | UserInfo の name (無ければ nickname) |
| 同じメールの既存アカウントへの自動の紐付け | しない | UserInfo の email_verified が true のときだけ |

UserInfo を使わないとき、Yahoo! JAPAN で初めて入った人はメールの無いアカウントになる。
既存のアカウントを持っている人は、先にそのアカウントでログインし、設定の「連携」から Yahoo! JAPAN を足してもらう。

UserInfo を使うとき、UserInfo の sub が id_token と違えば受けない。
