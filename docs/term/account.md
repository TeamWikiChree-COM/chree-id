# 用語: アカウント

仕組みの説明は [アカウントモデル](../ACCOUNTS.md) を参照。

| 語 | 意味 | 利用者に見えるか |
| --- | --- | --- |
| AuthIdentity (認証主体) | 認証手段の持ち主。アカウントではない。旧名 `ChreeAccount`、DB では `auth_identities` / `auth_identity_id` | 見えない |
| UserAccount (ユーザーアカウント) | 複数の ServiceAccount を束ねる人格。望む人だけが持つ | 見える |
| ServiceAccount (サービスアカウント) | サービス上の人格。サービスに渡す `sub` を持つ | 見える |
| Credential (認証手段) | AuthIdentity に属する認証の手段。種類は [認証](auth.md) を参照 | 見える |
| 主アドレス | AuthIdentity のメールアドレス。サービスに別のアドレスを割り当てていなければ、これが渡る | 見える |
| 追加アドレス | 主アドレスとは別に登録したアドレス。サービスごとに割り当てられる | 見える |
| origin (出自) | そのアカウントがどこから作られたかの記録 (`user` か `service`)。今の状態の判定には使わない | 見えない |

利用者に「アカウント」として見せるのは UserAccount と ServiceAccount の2つだけ。
