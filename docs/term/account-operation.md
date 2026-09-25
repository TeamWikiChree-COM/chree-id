# 用語: アカウントの操作

何が引き継がれるかなどの詳細は [アカウントモデル](../ACCOUNTS.md) を参照。

| 語 | コード上の名前 | 意味 |
| --- | --- | --- |
| 遅延登録 | `IssueServiceAccount` | サービスへのログインを機に、裏で ServiceAccount を発行する。利用者は登録操作をしない |
| 移行 | migrate、claim (引き取り) | ServiceAccount を本人の新しい UserAccount のものにする。画面や文書では「移行」と呼び、「引き取り」は使わない |
| 統合 | merge | ServiceAccount を本人の既にある UserAccount へ寄せる |
| 分離 | split | ServiceAccount を新しい AuthIdentity へ切り離す。統合の逆 |
| 停止 | suspend | `suspended_at` を立て、すべてのログイン経路を止める |
| 退会 | withdraw | `deleted_at` を立てる (あわせて `suspended_at` も立てる)。猶予 (`CHREEID_ACCOUNT_PURGE_DAYS`) を過ぎたら物理削除する |
| 移行チケット | claim ticket | 移行の画面へ入るための使い捨ての URL。サービスがサーバ間 API で発行する |
