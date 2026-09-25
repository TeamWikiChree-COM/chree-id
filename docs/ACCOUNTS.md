# アカウントモデル

各用語の定義は [用語リスト](term/README.md) にまとめている。

ChreeID の「アカウント」は1種類ではない。ここを取り違えると設計がねじれるので、用語をそろえておく。

## 4つの層

```text
AuthIdentity ─┬─ Credential      (N)
              ├─ UserAccount     (0..1)
              └─ ServiceAccount  (N)
```

| 層 | 役割 | 利用者に見えるか |
| --- | --- | --- |
| AuthIdentity | 認証主体。認証手段 (Credential) の持ち主 | 見えない |
| UserAccount | 複数の ServiceAccount を束ねる人格 | 見える |
| ServiceAccount | サービス上の人格。サービスに渡す `sub` を持つ | 見える |
| Credential | AuthIdentity に対する認証手段 (パスワード、パスキー、TOTP、外部IdP など) | 見える |

AuthIdentity はアカウントではない。利用者に「アカウント」として見せるのは UserAccount と ServiceAccount の2つだけ。

コード上は旧名の `ChreeAccount` や、DB の `auth_identities` / `auth_identity_id` として残っている。

## 典型的な流れ

1. 利用者がサービス (例: DokuFarm) にログインする。サービスは裏で ChreeID に ServiceAccount を発行させる。利用者は ChreeID の存在を意識しない (遅延登録)
2. 本人が望めば、その ServiceAccount を UserAccount のものにする (移行)
3. 別のサービスの ServiceAccount も、同じ UserAccount に寄せられる (統合)

ServiceAccount のままでも使い続けられる。UserAccount にするかどうかは本人が決める。

## 用語

遅延登録、移行、統合、分離、停止、退会などの意味は [用語リスト](term/account-operation.md) を参照。

## 移行、統合、分離で何が動くか

| もの | 移行 | 統合/分離 |
| --- | --- | --- |
| Credential | 動かさない (AuthIdentity がそのまま残るため) | 項目ごとに本人に選ばせて引き継ぐ。既定は全部引き継ぐ |
| パスキー | 移せない | 移せない |
| 外部IdP (Google、GitHub) | 動かさない | 持っていける |
| `sub` | 変わらない | 変わらない |

- パスキーは、認証器側に user handle が焼き込まれているので移せない。移った先で登録し直してもらう
- `sub` は ServiceAccount に属する。`auth_identity_id` は統合で書き換わるので、`sub` を AuthIdentity から作ってはいけない

## メールアドレスの扱い

- `auth_identities.email` に一意制約は無い。同じアドレスの AuthIdentity が複数あるのは正常な状態
- メールアドレスから誰かを決める処理は `ResolveByEmail` に集める
- 統合してよい根拠は「メールが一致したこと」ではなく「両方のアカウントを本人が証明できたこと」。一致は統合の候補として見せるところまでに留める

## 状態の判定

- 統合済みかどうかは UserAccount の行があるかで判定する。`origin` は出自の記録であって、今の状態を表さない
- ログインできるかどうかは `isSuspended()` だけで判定する。詳しくは [DECISIONS.md](DECISIONS.md)
