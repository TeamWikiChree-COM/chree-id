# CI (GitHub Actions)

`.github/workflows/` にある自動処理の一覧。どれも push で勝手に動くので、手で何かする必要は基本的に無い。

## ワークフロー

| ワークフロー | いつ動くか | やること |
| --- | --- | --- |
| [Test](../.github/workflows/test.yml) | `main` / `dev` への push と pull request | 翻訳の生成、PHPStan、テスト |
| [Deploy to server](../.github/workflows/deploy.yml) | Test が成功したあと (`main` なら本番、`dev` ならテスト環境)。手動でも動かせる | フロントエンドと翻訳をビルドして、サーバへ転送する |
| [Docs](../.github/workflows/docs.yml) | `main` への push。手動でも動かせる | PHPDoc から Doctum で HTML を作り、GitHub Pages に公開する |

## 流れ

```text
push ─→ Test ─(成功)─→ Deploy to server
  │
  └─(main のとき)─→ Docs ─→ GitHub Pages
```

- Test が失敗すると、デプロイは動かない
- Docs は Test を待たない。ドキュメントは本番の動作に関わらないため

## ブランチ

| ブランチ | push すると | 使いどころ |
| --- | --- | --- |
| `main` | 本番にデプロイされる | 普段の作業。開発者が少ないうちは `main` に直接 push してよい |
| `dev` | テスト環境にデプロイされる | 本番に出す前に動きを確かめたいとき |
| それ以外 | Test も Deploy も動かない | 大きめの変更を分けて進めたいとき。pull request を出すと Test が動く |

人数が少ないうちは、ブランチの運用を厳しくしない。複数人で同時に作業するようになったら、`dev` にまとめてから `main` に出す形や、`main` の保護を検討する。

## 公開先

| 内容 | URL | 備考 |
| --- | --- | --- |
| クラスとメソッドの一覧 | https://teamwikichree-com.github.io/chree-id/ | Docs が公開する |
| 本番 | https://id.wikichree.com/ | Deploy が転送する |

## 注意

- コミットメッセージに `[skip ci]` を書かない。Test が飛ぶとデプロイも黙って飛ぶ。詳しくは [デプロイ](DEPLOY.md)
- ビルド済みのフロントエンドのアセット (`public/build/`) と翻訳の生成物 (`generated/`) はコミットしない。Deploy が毎回作る
- Docs を初めて動かすときは、リポジトリの Settings → Pages で Source を「GitHub Actions」にしておく
