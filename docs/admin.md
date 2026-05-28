# Admin Panel

digestpipe の管理画面は、将来の内部管理画面を追加するための最小 foundation です。

現在の管理画面は `/admin` にあります。認証は Google OAuth のみを使用します。メールアドレスとパスワードによるログイン、registration、password reset、user invitation、public account management は実装していません。

## 設定

Google OAuth と管理者 allow-list は環境変数で設定します。

```env
DIGESTPIPE_ADMIN_ALLOWED_EMAILS=admin@example.test
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

`DIGESTPIPE_ADMIN_ALLOWED_EMAILS` は comma-separated list です。空白は無視され、メールアドレスは case-insensitive に照合されます。

allow-list が空の場合、管理画面にログインできるユーザーはいません。

実際の Google client ID、client secret、管理者メールアドレスは commit しないでください。

## OAuth Routes

```txt
GET /auth/google/redirect
GET /auth/google/callback
```

Route names:

```txt
auth.google.redirect
auth.google.callback
```

`/admin/login` は password login form ではなく Google OAuth redirect に進みます。

## Access Control

Google OAuth callback と Filament panel access check の両方で `DIGESTPIPE_ADMIN_ALLOWED_EMAILS` を確認します。

allow-list から削除されたユーザーは、既に local user record が存在していても `/admin` にアクセスできません。

OAuth access token、refresh token、authorization code、raw provider payload は保存しません。

## Local Dev Login Helper

Codex や開発者が local browser で Filament UI を確認するために、local-only の開発ログイン helper があります。

```txt
GET /_local/admin/login
```

Route name:

```txt
local.admin.login
```

この helper は disabled by default です。使用する場合は local の `src/.env` で明示的に有効化します。

```env
DIGESTPIPE_ADMIN_ALLOWED_EMAILS=admin@example.test
DIGESTPIPE_ADMIN_DEV_LOGIN_ENABLED=true
DIGESTPIPE_ADMIN_DEV_LOGIN_EMAIL=admin@example.test
```

安全条件:

- `APP_ENV` が `local` または `testing`
- `DIGESTPIPE_ADMIN_DEV_LOGIN_ENABLED=true`
- `DIGESTPIPE_ADMIN_DEV_LOGIN_EMAIL` が設定されている
- dev login email が `DIGESTPIPE_ADMIN_ALLOWED_EMAILS` に含まれている
- `User::canAccessPanel()` による Filament access check を通過する

この helper は production では使用しないでください。Google OAuth の production/admin authentication policy を置き換えるものではありません。

## 現在の範囲

この foundation では dashboard、Analysis Insights page、Source Insights page、Feed Sources resource、Feed Source detail page、Digest Item review resource、Positive Keywords resource、Negative Keywords resource を実装しています。

## Dashboard

Filament dashboard は `/admin` にあります。

Dashboard は直近 7 日間の Digest Item を対象に、selection behavior と pipeline health の operational visibility を表示します。

Phase 1 の dashboard MVP では次の情報を表示します。

- Selection KPI
- Selection status distribution
- Source breakdown
- Top positive keywords
- Top negative keywords
- Recent selected items
- Recent skipped items

Phase 2 では処理 pipeline の現在状態を確認するために、次の情報を追加しています。

- Article content status distribution
- Analysis status distribution
- Pipeline health KPI
- Latest pipeline activity
- Recent failed processing items

Phase Z-3a では Laravel Cloud API を使う Cloud Status widget を追加しています。

- Last deployment status
- Branch name
- Commit hash
- Commit message
- Commit author
- Started at / finished at
- Failure reason

Cloud Status は deployment status のみを表示します。Laravel Cloud API token と environment ID が未設定の場合は safe empty state を表示します。Environment metrics、database metrics、Commands API execution、logs viewer は含めていません。

Dashboard header の `Export Insights` action から、selection behavior の compact Markdown report を download できます。この export は ChatGPT などに貼り付けて selection tuning を検討するためのものです。

Export には summary、source breakdown、top positive/negative keywords、recent selected/skipped items、分析用 prompt が含まれます。Raw article content、raw HTML、full `analysis_json`、full `selection_result` JSON、API token や OAuth token は含めません。

この dashboard は compact な運用確認用です。Scheduler run history、analysis insights、source-specific deep dive は dashboard には含めていません。

## Analysis Insights

Analysis Insights page は `/admin/analysis-insights` にあります。

この page は日常監視用の dashboard ではなく、AI が生成した `analysis_json` の品質確認と将来の taxonomy 改善のための inspection page です。直近 30 日間の分析済み Digest Item を対象に、次の情報を表示します。

- `classification.content_type` breakdown
- `classification.content_type` by `source_key`
- Recent analysis samples
- `classification.confidence` distribution
- `classification.importance` distribution
- Low-confidence items (`confidence < 0.6`)

`content_type` は現在 free-form value であり、enum validation は行っていません。この page では保存済みの値を正規化せず、そのまま表示します。

この page は read-only です。分析結果の編集、再分析 action、prompt/schema の変更は行いません。

## Feed Sources

Feed Sources は DB-backed master data です。`src/config/digestpipe.php` ではなく `feed_sources` table に保存されます。

初期 Feed Source は database seeder で登録されます。Seeder は `key` が既に存在する record を上書きしないため、Filament で編集した値は通常の seed 実行では維持されます。

Filament では次の項目を作成・編集できます。

- `key`
- `name`
- `url`
- `language`
- `enabled`
- `analysis_enabled`
- `tier`
- `category`

`key` は Source Key として履歴を識別するため、既存 record の編集時は readonly です。

`sort_order` は通常の form field としては表示しません。Feed Sources table の reordering で管理します。

`analysis_enabled=true` は `enabled=false` の Feed Source には設定できません。

### Source Detail

Feed Source 一覧の view action から、source-specific detail page を開けます。

この page は直近 7 日間の Digest Item を `source_key` で絞り込み、1 つの Feed Source に関する運用シグナルを表示します。

- Source metadata
- Source-specific KPI counts and rates
- Selection status breakdown
- Article content status breakdown
- Analysis status breakdown
- Top positive keywords
- Top negative keywords
- Source-specific `classification.content_type` breakdown
- Recent selected items
- Recent skipped items
- Recent failed items

Source Detail page は read-only です。Feed Source 設定の編集は既存の edit action で行い、分析結果や Digest Item は編集しません。

この page は source settings と selection behavior の調整を支援するための表示です。Engagement metrics、discussion summaries、command run logging、state transition history は実装していません。

### Source Insights

Source Insights page は `/admin/source-insights` にあります。

この page は直近 7 日間の Digest Item を対象に、Feed Source を横断比較する read-only page です。Source Detail が 1 つの source の drill-down であるのに対して、Source Insights は source 同士の value と pipeline health を比較します。

Main table では次の値を表示します。

- `source_key`
- total Digest Items
- selected rate
- skipped rate
- pending rate
- analysis completed rate
- failure rate
- average selection score

率の分母はその source の total Digest Items です。Pending は `selection_status=pending` と `selection_status=needs_content` を含めます。Failure rate は `article_content_status=failed` または `analysis_status=failed` の Digest Item 数を total で割った値です。

Source Detail overview でも Selected、Skipped、Pending、Content Failed、Analysis Failed、Analysis Completed は `104 (48.23%)` のように count と rate を併記します。Source の価値判断では raw count だけでなく rate を優先して確認してください。

Manual selected sample quality、manual good/bad rating、engagement metrics はまだ実装していません。選択済み item の品質評価 UI は今後の別タスクです。

## Digest Item Review

Digest Item review resource は `/admin/digest-items` にあります。

この resource は human review 用の private admin UI です。Default list view は Ready for Review を対象にします。Ready for Review は次の条件をすべて満たす Digest Item です。

- `selection_status=selected`
- `article_content_status=completed`
- `analysis_status=completed`

List page には Selected、Skipped、Unrated、Rated Good、Rated Bad、Content fetched、Analysis completed、Source の filters があります。Index では raw article content は表示せず、title、source、selection / content / analysis status、content type、importance、confidence、manual rating を確認します。

View page では title、source URL、discussion URL、selection reason、matched positive / negative keywords、article content text、analysis brief、detailed summary、key points、importance、confidence、limitations を確認できます。Raw HTML は表示しません。

Manual rating は `digest_items.manual_rating` に保存されます。

- `null`: Unrated
- `-1`: Bad
- `1..5`: Good star rating

Good と Bad は 1 つの `manual_rating` value で表現するため相互排他的です。Good / Bad を設定すると `manual_rated_at` に保存時刻を入れ、Clear すると `manual_rating` と `manual_rated_at` を `null` に戻します。

Manual rating は将来 Source Insights の source-level quality metrics に使う予定です。この段階では `manual_good_rate`、multi-user review、public review UI、AI evaluation は実装していません。

## Selection Keywords

Selection Keywords は DB-backed master data です。`src/config/digestpipe.php` の `selection.positive_keywords` / `selection.negative_keywords` ではなく、`selection_keywords` table に保存されます。

初期 Selection Keyword は database seeder で登録されます。Seeder は `type` + `keyword` が既に存在する record を上書きしないため、Filament で編集した値は通常の seed 実行では維持されます。

DB 上では positive / negative keyword を 1 つの table に保存し、`type` で分類します。アプリケーション code は repository から positive / negative の keyword score map として個別に読み込みます。

Filament admin UI では、日常的な編集を分かりやすくするために次の 2 つの resource として表示します。

- Positive Keywords
- Negative Keywords

どちらも同じ `selection_keywords` table と `SelectionKeyword` model を使います。`type` は resource によって自動設定されるため、form では選択しません。generic な Selection Keywords resource は admin navigation には表示しません。

Filament では次の項目を作成・編集できます。

- `keyword`
- `match_mode`
- `score`
- `enabled`
- `locale`
- `category`
- `notes`

`match_mode` は keyword の一致方式です。

- `contains`: 大文字小文字を区別しない UTF-8 部分一致です。日本語や CJK keyword、広い substring match に使います。
- `word_boundary`: 大文字小文字を区別しない standalone token match です。`CLI`、`DeFi`、`API`、`S3`、`IAM` など短い英単語・略語が長い英数字列の一部に一致する false positive を避けるために使います。
- `exact_phrase`: 大文字小文字を区別しない literal phrase match です。`GitHub Actions`、`PHP-CS-Fixer`、`AGENTS.md` など語句や記号を含む keyword に使います。

`regex` mode は intentionally not supported です。keyword は正規表現としてではなく literal string として扱います。

`score` は Positive Keywords では `1..100`、Negative Keywords では `-100..-1` の整数です。`score=0` は許可しません。

Default keyword set は false positive を避けるため、広すぎる `token` / `tokens` / `トークン` を negative default から外し、`crypto token` など crypto 文脈の phrase に寄せています。Positive default も broad な `AWS` ではなく、AWS service 名や development、security、agent、tooling、cloud/CDN 系の keyword を使います。

`sort_order` は通常の form field としては表示しません。Positive / Negative それぞれの table reordering で管理します。

Selection threshold は引き続き config-backed です。

今後の候補:

- selection thresholds
- source metadata
- read-only Digest Item operational views
- analysis/content type reports
