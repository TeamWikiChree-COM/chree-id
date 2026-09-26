# Wiki Hub

連携しているサービス (DokuFarm・WikiChree.COM) のウィキを、利用者ごとに1画面へまとめる。
画面は `/plugins/wiki-hub`。ダッシュボードの「利用可能なプラグイン」から入る。

## ChreeID 側の設定 (.env)

サービスごとに3つ揃ったものだけ使う。揃っていないサービスには問い合わせない。

```
WIKI_HUB_DOKUFARM_CLIENT_ID=   ChreeID に登録されている DokuFarm の client_id
WIKI_HUB_DOKUFARM_ENDPOINT=    下の API の URL (例: https://dokufarm.example/api/chreeid/wikis)
WIKI_HUB_DOKUFARM_TOKEN=       共有の鍵。サービス側の .env にも同じ値を置く

WIKI_HUB_WIKICHREE_CLIENT_ID=
WIKI_HUB_WIKICHREE_ENDPOINT=
WIKI_HUB_WIKICHREE_TOKEN=
```

鍵は `openssl rand -hex 32` などで作る。client_secret を流用しないのは、
ChreeID 側にはハッシュしか残っていないため。

## サービス側が用意するAPI

```
GET {endpoint}?sub={sub}&service_user_id={service_user_id}
Authorization: Bearer {共有の鍵}
X-Wiki-Hub-Token: {共有の鍵}      (同じ値。Authorization が届かない環境向け。どちらか一方で受ければよい)
Accept: application/json
```

- `sub`: ChreeID が OIDC で渡した sub。OIDC でログインしている利用者ならこれで引ける
- `service_user_id`: サービス側の識別子。ChreeID がサービスアカウントを発行した利用者ならこれで引ける
- どちらか片方しか来ないことがある。来たほうで利用者を探す

### 期待するレスポンス

`200`:

```json
{
    "wikis": [
        {
            "name": "ウィキ名",
            "url": "https://…/",
            "icon_url": "https://…/logo.png",
            "settings_url": "https://…/settings",
            "views": 1234,
            "updated_at": "2026-09-26T12:00:00+09:00"
        }
    ]
}
```

| 項目 | 必須 | 内容 |
| --- | --- | --- |
| `name` | ○ | ウィキ名 |
| `url` | ○ | ウィキのトップ |
| `icon_url` | | ウィキのロゴ (絶対 URL)。無ければ省略か null。ChreeID 側は頭文字で代わりを出す |
| `settings_url` | | そのウィキの設定画面。無ければ省略か null |
| `views` | | アクセス数 (整数)。数えていなければ省略か null |
| `updated_at` | | 最終更新 (ISO 8601) |

- その利用者がサービス側にいない: `404` (失敗ではなく「0件」として扱う)
- 鍵が異なる: `401`
- それ以外の失敗: `5xx`。画面にはそのサービスだけ「取得できませんでした」と表示する

返すのはその利用者が管理者 (持ち主) のウィキだけ。 他人のウィキを返すと、ChreeID の画面から設定画面の URL が漏れる。

## 取得のふるまい
- 1サービスあたり `WIKI_HUB_TIMEOUT` 秒 (既定 5) で打ち切る
- 結果は `WIKI_HUB_CACHE_SECONDS` 秒 (既定 300) 持っておく。アクセス数は厳密でなくてよい
