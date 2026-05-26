# digestpipe 用語集

これは digestpipe の開発に使用する用語をまとめた文書です。

## 基本概念

### digestpipe

RSS などの外部情報源から Feed Item を取得し、Digest Item として保存し、本文取得、選考、分析を行い、構造化 Digest JSON を返す Laravel アプリケーションです。

正式名称は `Distilled Information Gateway for News Feed Transformation Pipeline` です。

### 構造化 Digest JSON (Structured Digest JSON)

最終的に出力する JSON 形式のデータです。

Digest Item の source metadata、article metadata、selection metadata、`analysis_json`、processing metadata をまとめた JSON レコードを指します。

### 中間表現 (Intermediate Representation)

Downstream アプリケーションが利用しやすいように整理した中間データです。

現在の digestpipe では `analysis_json` が主な中間表現です。
digestpipe は構造化 Digest JSON 以降の最終的な出力への責務を持ちません。

### Downstream Application

digestpipe の `digestpipe:digests:export` または Article JSON API を利用する後段アプリケーションです。

Downstream アプリケーションは digestpipe から受け取った構造化 Digest JSON を、表示、通知、翻訳、読み上げなどに利用できます。

## 情報源

### 情報源 (Source)

digestpipe が Feed Item を取得する外部サイトや媒体の総称です。

### RSS フィード情報源 / Feed Source

`src/config/digestpipe.php` の `feed_sources` に定義された RSS / Atom feed の設定です。

主な Field:

- `key`: RSS フィード情報源を一意に識別する `source_key`
- `name`: 表示名
- `url`: RSS / Atom feed URL
- `language`: feed の主な言語
- `enabled`: データ取得の対象かどうか
- `analysis_enabled`: 分析対象候補
- `tier`: 採用段階を表す設定メタデータ
- `category`: 大まかな分類を表す設定メタデータ

現在の `FeedSource` ランタイムは `key` `name` `url` `language` `enabled` を読み込みます。
`analysis_enabled` `tier` `category` は設定上のメタデータとして定義されています。

### Source Key / `source_key`

RSS フィード情報源を識別する安定したキーです。

例:

| source_key | name | category | language |
| --- | --- | --- | --- |
| `hacker_news` | Hacker News | `aggregator` | `en` |
| `lobsters_programming` | Lobsters / Programming | `programming` | `en` |
| `php_weekly` | PHP Weekly | `php` | `en` |
| `laravel_news` | Laravel News | `laravel` | `en` |
| `aws_news` | AWS News | `aws` | `en` |
| `zenn_php` | Zenn / PHP | `php` | `ja` |
| `zenn_laravel` | Zenn / Laravel | `laravel` | `ja` |

### Source Tier / `tier`

RSS フィード情報源の採用段階です。

現在の値:

- `core`: 通常の取得・分析対象として有効化している主要な RSS フィード情報源
- `candidate`: 将来的な RSS フィード情報源の候補 (現在は `enabled=false` `analysis_enabled=false`)

### Source Category / `category`

RSS フィード情報源の大まかな分類です。

例:

- `aggregator`
- `programming`
- `web`
- `linux`
- `devops`
- `php`
- `laravel`
- `aws`
- `hardware`
- `self-hosted`

## Feed / Digest / Article

### Feed Item

RSS / Atom feed から読み取った 1 件の Item / Entry です。

実装上は `FeedItem` として扱われ、`externalId` `sourceUrl` `discussionUrl` `title` `excerpt` `publishedAt` を持ちます。

### Digest Item

digestpipe が DB の `digest_items` table で保存・管理する処理単位です。

実装上は `DigestItem` モデルです。
Feed Item 由来の情報、本文取得状態、選考状態、分析状態と Analysis JSON を同一レコードで管理します。

現行実装では `DigestItem` / `digest_items` として扱っています。

### Source Article (Article)

Feed Item の `link` などが指す元記事です。

DB では主に `source_url` に保存されます。
例えば、Hacker News RSS の場合であれば `link` は元記事 URL で、さらに `comments` を Hacker News Discussion URL として扱います。

### Discussion URL / `discussion_url`

Digest Item に紐づく Discussion / Comment ページの URL です。

現在はメタデータとして保存します。
Hacker News comments の取得や discussion summarization は未実装です。

### Excerpt

RSS / Atom feed に含まれる Description / Summary に相当する短い本文候補です。

RSS は記事候補を発見するための入力として扱うため `excerpt` が記事本文であるとは限りません。
Hacker News RSS の `Comments` リンクのような値は、記事本文として扱わないようにしています。

### Extracted Article Text / `article_content_text`

元記事 HTML から ルールベースで抽出した本文テキストです。

`FetchDigestItemArticleContentJob` が HTML を取得します。
`ArticleTextExtractor` が `article` `main` `body` などから意味のあるテキストを抽出します。
Raw HTML は保存しません。

## Pipeline

### 情報取得 (Feed Fetch)

RSS フィード情報源を取得して Feed Item を parse する処理です。

Command:

```bash
php artisan digestpipe:feeds:fetch
```

### 取り込み (Ingestion)

parse 済み Feed Item を Digest Item として DB に保存する処理です。

現行実装では `DigestItem` モデルとして保存します。

重複判定には `source_key` と `identity_hash` を使います。
新規作成時は `selection_status` `article_content_status` `analysis_status` が `pending` になります。

### 選考 (Selection)

Digest Item を後続の高コスト処理へ進めるかどうかを決めるルールベースの選別処理です。

現在は keyword-based selection です。
OpenAI は呼びません。

選考は処理対象を決める状態変更です。
API の `source` / `from` / `limit` などによる取得の絞り込みとは別概念です。

### 本文取得 (Article Content Fetch)

`source_url` から元記事 HTML を取得し、本文テキストを抽出して `article_content_text` に保存する処理です。

Job:

```txt
FetchDigestItemArticleContentJob
```

### 分析 (Analysis / Article Analysis)

Digest Item 本文または利用可能な入力を元の言語のまま分析して Analysis JSON を生成する処理です。

OpenAI Driver では OpenAI Responses API を使用します。

対応 job:

```txt
AnalyzeDigestItemJob
```

### Processing Orchestration / Orchestrator

Digest Item の状態を見て、次に必要な Job を 1 Item につき 1 つだけ Dispatch する制御です。

Command:

```bash
php artisan digestpipe:items:enqueue-processing
```

処理順:

```txt
feed fetch
  -> pre-content selection
  -> article content fetch
  -> post-content selection
  -> article analysis
  -> structured digest export / private API
```

### Export

処理が完了済みの Analysis JSON を Downstream アプリケーションが扱いやすい構造化 Digest JSON として出力する処理です。

Command:

```bash
php artisan digestpipe:digests:export
```

## 選考 (scoring)

### Selection Score / `selection_score`

選考対象テキストから算出したルールベースの整数値のスコアです。

`default_score` から開始して、設定された `positive_keywords` と `negative_keywords` の重みを title / excerpt / article content にマッチした分だけ加減算します。

固定の最大値・最小値はありません。
設定されたキーワードの重みとマッチ数に依存します。

`selection_score` は `analysis.classification.importance` とは別物です。
`selection_score` は 分析前の処理ゲートで、`importance` は 分析後に AI が生成するメタデータです。

### Positive Keyword / `positive_keywords`

Selection Score を加点するキーワードと重みの設定です。

「PHP」「Laravel」「AWS」「Linux」「Docker」など、処理したい Digest Item に関係する語が含まれます。
英語と日本語のキーワードをどちらも扱うので両方の設定が必要です。

### Negative Keyword / `negative_keywords`

Selection Score を減点するキーワードと重みの設定です。

「Crypto」「Web3」「startup funding」など、現時点で優先度を下げたい語が含まれます。
強い Negative Keyword は本文取得前でも強制スキップされます。

### Analysis Threshold / `analysis_threshold`

後工程の選考 (post-content selection) で `selected` と判定するための閾値です。

default:

```txt
analysis_threshold = 1
```

Article Content Fetch 後に `selection_score >= analysis_threshold` のアイテムは `selected` になります。

### Skip Threshold / `skip_threshold`

本文取得前でも明確に `skipped` と判定するために、前工程の選考 (pre-content selection) で Hard Skip する閾値です。

default:

```txt
skip_threshold = -50
```

Article Content Fetch 前に `selection_score <= skip_threshold` のアイテムは `skipped` になります。
それ以外は `needs_content` として本文取得へ進みます。

### Two-Phase Selection

本文取得前と本文取得後で選考ルールを分ける設計です。

#### 前工程の選考 (pre-content selection)

- 入力: `title`、`excerpt`
- `selection_score <= skip_threshold`: `skipped`
- それ以外: `needs_content`

#### 後工程の選考 (post-content selection)

- 入力: `title`、`excerpt`、`article_content_text`
- `selection_score >= analysis_threshold`: `selected`
- それ以外: `skipped`

この設計により Hacker News のように RSS Excerpt が弱い Source でも、タイトルだけで通常閾値を下回っただけでは早期スキップされません。

### 選考理由 (Selection Result / `selection_result`)

DB に保存する選考の詳細 JSON です。

現在の Shape:

```json
{
  "score": 12,
  "status": "selected",
  "matched_good_keywords": [
    "Laravel"
  ],
  "matched_bad_keywords": [],
  "reason": "above_analysis_threshold"
}
```

Article JSON API では Internal Details を出力しないで `selection.status` と `selection.score` だけを返します。

### Selection Rollback

選考でスキップされたアイテムを再評価できる状態へ戻す手順です。

Command:

```bash
php artisan digestpipe:selection:rollback --source=hacker_news --status=skipped
```

現在は `--status=skipped` のみ対応します。article content fetch や分析に進んだアイテムは対象外です。

## Status Field

### `selection_status`

選考の状態です。

現在の主な値:

| value | 意味                           |
| --- |------------------------------|
| `pending` | 選考が未評価                       |
| `needs_content` | 前工程の選考で強制スキップされなかったので本文取得が必要 |
| `selected` | 後工程の選考を通過した分析対象              |
| `skipped` | 選考により後続処理の対象外                |

### `article_content_status`

Article Content Fetch の状態です。

現在の主な値:

| value | 意味 |
| --- | --- |
| `pending` | 本文取得未処理 |
| `queued` | 本文取得 job を dispatch 済み |
| `processing` | 本文取得 job が処理中 |
| `completed` | 本文取得と抽出が完了 |
| `failed` | 予期しない取得・抽出失敗 |
| `skipped` | URL 不在、非 HTML、空 body、本文抽出不能などで意図的に skip |

### `analysis_status`

article analysis の状態です。

現在の主な値:

| value | 意味                                      |
| --- |-----------------------------------------|
| `pending` | 分析未処理                                   |
| `queued` | 分析ジョブを dispatch 済み                      |
| `processing` | 分析ジョブを処理中                               |
| `completed` | Analysis JSON を生成して保存済み               |
| `failed` | Provider Error / Invalid Response などで失敗 |
| `skipped` | 分析に入力が使用できないので意図的にスキップ                  |

### Ready for Digest / `readyForDigest()`

downstream に提供可能な Digest Item の状態です。

現在の条件:

```txt
analysis_status = completed
analysis_json is not null / not empty
```

実装上は `DigestItem::readyForDigest()` と `DigestItem::hasCompletedAnalysis()` が同じ条件を見ます。

## Analysis JSON

### `analysis_json`

AI 分析により生成されて DB に保存される JSON です。

現在の Schema version は `1.0` です。

最上位 field:

- `schema_version`
- `source_language`
- `title`
- `content`
- `classification`

### `schema_version`

Analysis JSON の Schema version です。

現在:

```txt
1.0
```

### `source_language`

分析した Digest Item の主な言語です。

OpenAI Analysis は、原則として Source Language のまま出力します。
翻訳は digestpipe の責務ではありません。

### `title.original`

元のタイトルです。

### `title.normalized`

下流で扱いやすいように正規化したタイトルです。

### `content.brief`

Digest Item の内容を短く説明するテキストです。

### `content.detailed_summary`

downstream application が読み上げ、再構成などに使える程度の文脈を持つ要約文です。

### `content.key_points`

記事中の重要な具体的事実や論点のリストです。

空配列は許可されません。

### `content.background`

必要な背景情報です。
ない場合は `null` を許可します。

### `content.why_it_matters`

記事がなぜ重要か、または downstream application で扱う意味があるかを説明するフィールドです。
ない場合は `null` を許可します。

### `content.limitations`

分析上の制約や不確実性を説明するフィールドです。

例:

- Article Extraction が弱い
- Source Text が短い
- Paywall / Cookie Wall の可能性
- Context が不足している

ない場合は `null` を許可します。

### `classification.content_type`

分析対象アイテムの Content Type を表す分類ラベルです。

Validation:

- type: `string`
- required
- empty string は不可
- enum は未定義

現時点では固定 Enum として Enforce していません。

実際の値の揺れを確認するために、次のコマンドがあります。

```bash
php artisan digestpipe:analysis:report
```

### `classification.topics`

記事の大まかなトピック・ラベルのリストです。

Validation:

- type: `array<string>`
- required
- empty array は不可
- empty string item は不可
- enum は未定義

`topics` は完全に統制された taxonomy ではなく、downstream application 向けの粗い digest signal です。

### `classification.entities`

記事に登場する人名、組織名、製品名、プロジェクト、サービスなどの固有名詞リストです。

Validation:

- type: `array<string>`
- required
- empty array は許可
- empty string item は不可
- enum はありません

entity は canonical ID へ正規化していません。
たとえば `AWS` と `Amazon Web Services` の alias deduplication は現在実装していません。

### `classification.importance`

AI モデルが判断する digest signal としての重要度です。

Validation:

- type: `integer`
- required
- range: `1` から `5`

目安:

| value | 意味                      |
| --- |-------------------------|
| `1` | 重要度が低い / 狭い・軽微または偶発的な内容 |
| `2` | やや低い / 関係はあるが優先度は高くない   |
| `3` | 通常 / 候補として妥当な標準値        |
| `4` | 高い / 候補として非常に有用         |
| `5` | 非常に高い / 候補として最有力        |

`importance` は現在、digestpipe 内の processing decision には使っていません。
API / export で downstream application に渡すメタデータです。

### `classification.confidence`

AI モデルが、与えられた入力テキストに基づいて分析結果の信頼度を見積もった値です。

Validation:

- type: `number`
- required
- range: `0.0` から `1.0`

目安:

| range | 意味                                        |
| --- |-------------------------------------------|
| `0.0-0.2` | 非常に低い / 入力が欠けている・弱いまたは有用でない可能性が高い         |
| `0.3-0.5` | 低い / signal はあるが重要な context が不足している可能性がある |
| `0.6-0.8` | 通常 / 利用に十分だが不確実性は残る                       |
| `0.9-1.0` | 高い / 入力が十分で分析が根拠づけられている可能性が高い             |

低い `confidence` は `content.limitations` と一緒に読む想定です。

## API / export

### Article JSON API

分析済みの Digest Item を返す private read-only API です。

Endpoint:

```txt
GET /api/articles
GET /api/articles/{id}
```

認証:

```txt
auth:sanctum
abilities:digests:read
```

### Digest Export Item / Digest Record

`DigestExportItemBuilder` が生成する、CLI export と API で共通利用する Item Shape です。

Top-Level field:

- `id`
- `source`
- `article`
- `selection`
- `analysis`
- `processing`

raw `article_content_text` は含めません。

### `source`

情報源のメタデータです。

field:

- `key`
- `name`
- `feed_url`

### `article`

Digest Item のメタデータです。

field:

- `title`
- `url`
- `discussion_url`
- `published_at`
- `fetched_at`

### `selection`

API に含める選考のメタデータです。

field:

- `status`
- `score`

`matched_good_keywords` `matched_bad_keywords` `reason` は API には出しません。

### `analysis`

保存済み `analysis_json` をそのまま返すフィールドです。

API は `analysis` を翻訳しません。

### `processing`

分析処理のメタデータです。

field:

- `analysis_model`
- `analyzed_at`

## Commands

### `digestpipe:feeds:fetch`

Enabled な情報源から RSS / Atom feed を取得し、新しい Digest Item を保存します。

### `digestpipe:items:enqueue-processing`

State-Aware Orchestrator コマンドです。

Option:

- `--limit`
- `--per-source-limit`
- `--dry-run`
- `--source`
- `--stage=content|analysis`

### `digestpipe:digests:export`

完了済み Analysis Record を構造化 Digest JSON として標準出力します。

Option:

- `--limit`
- `--source`
- `--topic`
- `--content-type`
- `--from`
- `--to`
- `--format=json|jsonl`

### `digestpipe:selection:report`

選考結果を集計する read-only report コマンドです。

Laravel Cloud などで直接 DB を見にくい場合の運用観測に使います。

### `digestpipe:selection:rollback`

指定した情報源 の `skipped` Selection State を `pending` に戻す Rollback コマンドです。

現在は `--status=skipped` のみ対応します。

### `digestpipe:analysis:report`

完了済み `analysis_json` の `classification.content_type` 分布を確認する read-only report コマンドです。

Content Type の Enum を後で整理する観測用です。

### `digestpipe:users:create-api-user`

API 用の user を作成または再利用して Sanctum Personal Access Token を発行します。

### `digestpipe:users:rotate-api-token`

API 用の Sanctum Personal Access Token をローテートします。

## Infrastructure / operations

### Laravel Cloud

本番デプロイする運用環境です。

DB には MySQL を利用する前提です。
Laravel Cloud Serverless Postgres / Neon 用の接続 workaround は使用しない予定です。

### root `composer.lock`

Repository root に置いている Laravel Cloud framework detection 用の lock file です。

Authoritative Composer Project は `src/composer.json` および `src/composer.lock` です。
ルート直下の `composer.lock` は通常の依存更新で厳密同期する対象ではありません。

### Laravel Scheduler

定期実行の定義です。

現在の schedule:

```txt
digestpipe:feeds:fetch: every 10 minutes, withoutOverlapping(15)
digestpipe:items:enqueue-processing --limit=100 --per-source-limit=10: every 5 minutes, withoutOverlapping(10)
```

### Queue Worker

Laravel Queue Job を処理する Worker です。

Laravel Cloud では App Cluster Background Process として次のようなコマンドを使う想定です。

```bash
php artisan queue:work database --sleep=3 --tries=3 --timeout=120 --backoff=30
```

### `GET /`

一般には公開しません。

現在はキャッシュ可能なプレーンテキストの `404 Not Found` を返します。

### `GET /up`

Laravel 標準の health endpoint です。

認証不要で health check や Laravel Cloud wake polling に使います。
内部データは返しません。

## 混同しやすい用語

### Selection と Filtering

選考 (selection) は、アイテムを後続の processing pipeline に進めるかどうかを決める状態変更です。

フィルタリング (filtering) は、API が指定された条件により結果を絞り込む Read Operation です。

### Source URL と Discussion URL

`source_url` は元記事 URL です。

`discussion_url` は Hacker News comments などの discussion page URL です。

現在は discussion comments の取得や要約は行っていません。

### Selection Score と Importance

`selection_score` は Deterministic Keyword Scoring による pre-analysis gate です。

`analysis.classification.importance` は AI 分析後に生成される digest metadata です。

### Analysis と Translation

分析 (analysis) は元データの言語のまま構造化 Digest JSON を生成する処理です。

翻訳 (translation) は現行 primary pipeline ではありません。
翻訳、リライト、ナレーションは downstream application の責務です。
