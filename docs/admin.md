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

この foundation では dashboard、Feed Sources resource、Selection Keywords resource を実装しています。

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

この dashboard は compact な運用確認用です。Command run history、scheduler run history、analysis insights、source detail page はまだ実装していません。

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

## Selection Keywords

Selection Keywords は DB-backed master data です。`src/config/digestpipe.php` の `selection.positive_keywords` / `selection.negative_keywords` ではなく、`selection_keywords` table に保存されます。

初期 Selection Keyword は database seeder で登録されます。Seeder は `type` + `keyword` が既に存在する record を上書きしないため、Filament で編集した値は通常の seed 実行では維持されます。

DB 上では positive / negative keyword を 1 つの table に保存し、`type` で分類します。アプリケーション code は repository から positive / negative の keyword score map として個別に読み込みます。

Filament では次の項目を作成・編集できます。

- `keyword`
- `type`
- `score`
- `enabled`
- `locale`
- `category`
- `notes`

`score` は `type=positive` では正の整数、`type=negative` では負の整数である必要があります。

`sort_order` は通常の form field としては表示しません。Selection Keywords table の reordering で管理します。

Selection threshold は引き続き config-backed です。

今後の候補:

- selection thresholds
- source metadata
- read-only Digest Item operational views
- analysis/content type reports
