# Spovie ドキュメント

| ドキュメント | 内容 |
|---|---|
| [01-requirements.md](01-requirements.md) | 要件定義（目的・ユーザー・機能要件・非機能要件・スコープ・著作権ポリシー） |
| [02-basic-design.md](02-basic-design.md) | 基本設計（構成図・機能一覧・**ER 図**・権限設計・主要フロー） |
| [03-detailed-design.md](03-detailed-design.md) | 詳細設計（画面仕様・**画面遷移図**・アノテーションの座標設計・リアルタイム・キャッシュ・エラー処理） |
| [api-endpoints.md](api-endpoints.md) | **API 仕様**（全エンドポイント。`route:list` と FormRequest から自動生成、CI でずれを検知） |
| [04-setup.md](04-setup.md) | 環境構築手順（ローカル・テスト・Pusher・R2） |
| [deployment.md](deployment.md) | デプロイ手順（Railway / Vercel / R2 / Pusher）と環境変数一覧 |
| [pwa.md](pwa.md) | PWA の実装・キャッシュ戦略・Lighthouse 結果 |
| [self-assessment.md](self-assessment.md) | 評価基準に対する自己評価（根拠つき・事実ベース） |

> 設計書は Markdown が正本です。Notion など他のツールに載せる場合は、ここを元に転記してください（Mermaid の図は Notion のコードブロックに貼ると描画できます）。
