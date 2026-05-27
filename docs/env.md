# Environment Variables

この文書は `src/.env.example` に定義している、Laravel 標準以外の digestpipe 固有・外部連携設定を説明します。

Laravel 標準の `APP_*`、`DB_*`、`CACHE_*`、`SESSION_*`、`QUEUE_*`、`REDIS_*`、`AWS_*` などは、この文書では扱いません。

実際の設定値は `src/.env` または Laravel Cloud の Environment Variables に設定します。real API key、OAuth client secret、管理者メールアドレスは commit しないでください。

## Google OAuth

Filament admin panel の Google OAuth login で使います。

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

| Name | Description |
| --- | --- |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID です。未設定の場合、実際の Google OAuth login は開始できません。 |
| `GOOGLE_CLIENT_SECRET` | Google OAuth client secret です。secret なので commit しないでください。 |
| `GOOGLE_REDIRECT_URI` | Google OAuth callback URL です。通常は `${APP_URL}/auth/google/callback` を使います。Google Cloud 側の authorized redirect URI と一致させます。 |

OAuth routes:

```txt
GET /auth/google/redirect
GET /auth/google/callback
```

## OpenAI

OpenAI-backed analysis driver が OpenAI Responses API を呼び出すための設定です。

```env
OPENAI_API_KEY=
OPENAI_MODEL="gpt-4o-mini"
OPENAI_REQUEST_TIMEOUT=60
OPENAI_MAX_RETRIES=2
```

| Name | Description |
| --- | --- |
| `OPENAI_API_KEY` | OpenAI API key です。`DIGESTPIPE_AI_DRIVER="openai"` のときに必要です。real key は commit しないでください。 |
| `OPENAI_MODEL` | OpenAI driver の fallback model です。`DIGESTPIPE_ANALYSIS_MODEL` が未設定の場合の分析 model としても使われます。 |
| `OPENAI_REQUEST_TIMEOUT` | OpenAI API request timeout seconds です。 |
| `OPENAI_MAX_RETRIES` | OpenAI API request retry count です。 |

Automated tests と安全な local development では real OpenAI API を呼ばないようにします。

## AI Driver

分析処理で使う AI driver を選びます。

```env
DIGESTPIPE_AI_DRIVER="fake"
```

| Name | Description |
| --- | --- |
| `DIGESTPIPE_AI_DRIVER` | `fake` または `openai` を指定します。`fake` は tests と安全な local development 用です。`openai` は OpenAI-backed analysis を使います。 |

## Article Analysis

Digest Item の article content を分析し、`analysis_json` を生成する処理の設定です。

```env
DIGESTPIPE_ANALYSIS_MODEL="gpt-4o-mini"
DIGESTPIPE_ANALYSIS_BATCH_LIMIT=10
DIGESTPIPE_ANALYSIS_DAILY_LIMIT=100
DIGESTPIPE_ANALYSIS_MAX_INPUT_CHARS=8000
DIGESTPIPE_ANALYSIS_OUTPUT_SCHEMA_VERSION="1.0"
```

| Name | Description |
| --- | --- |
| `DIGESTPIPE_ANALYSIS_MODEL` | 分析に使う model 名です。未設定の場合は `OPENAI_MODEL`、それも未設定の場合は application default を使います。 |
| `DIGESTPIPE_ANALYSIS_BATCH_LIMIT` | 1 回の analysis command/job batch で扱う上限です。 |
| `DIGESTPIPE_ANALYSIS_DAILY_LIMIT` | 1 日あたりの analysis 実行上限です。OpenAI usage を抑えるための安全弁です。 |
| `DIGESTPIPE_ANALYSIS_MAX_INPUT_CHARS` | analysis input に渡す article text の最大文字数です。長い本文をそのまま AI に渡しすぎないために使います。 |
| `DIGESTPIPE_ANALYSIS_OUTPUT_SCHEMA_VERSION` | 保存する `analysis_json` の schema version です。現在の primary schema は `1.0` です。 |

## Article Content Fetching

RSS item から source article を取得し、分析に使う本文を抽出する処理の設定です。

```env
DIGESTPIPE_CONTENT_FETCH_TIMEOUT=15
DIGESTPIPE_CONTENT_MAX_BYTES=1048576
DIGESTPIPE_CONTENT_MAX_CHARS=8000
DIGESTPIPE_CONTENT_MIN_CHARS=200
DIGESTPIPE_CONTENT_USER_AGENT="digestpipe/0.1 (+structured digest pipeline)"
```

| Name | Description |
| --- | --- |
| `DIGESTPIPE_CONTENT_FETCH_TIMEOUT` | source article HTTP request timeout seconds です。 |
| `DIGESTPIPE_CONTENT_MAX_BYTES` | 取得する response body の最大 bytes です。過大な response を避けるために使います。 |
| `DIGESTPIPE_CONTENT_MAX_CHARS` | 保存・分析候補にする extracted text の最大文字数です。 |
| `DIGESTPIPE_CONTENT_MIN_CHARS` | article content として有効とみなす最小文字数です。短すぎる抽出結果は本文取得失敗として扱われます。 |
| `DIGESTPIPE_CONTENT_USER_AGENT` | article content fetch に使う User-Agent です。 |

## Admin Access

Filament admin panel の access control と local-only dev login helper の設定です。

```env
DIGESTPIPE_ADMIN_ALLOWED_EMAILS=
DIGESTPIPE_ADMIN_DEV_LOGIN_ENABLED=false
DIGESTPIPE_ADMIN_DEV_LOGIN_EMAIL=
```

| Name | Description |
| --- | --- |
| `DIGESTPIPE_ADMIN_ALLOWED_EMAILS` | 管理画面への access を許可する email list です。comma-separated list で指定します。空白は無視され、case-insensitive に照合されます。空の場合は誰も管理画面に入れません。 |
| `DIGESTPIPE_ADMIN_DEV_LOGIN_ENABLED` | local-only dev login helper を有効化する flag です。default は `false` です。production では有効化しないでください。 |
| `DIGESTPIPE_ADMIN_DEV_LOGIN_EMAIL` | local-only dev login helper が session login する email です。この email も `DIGESTPIPE_ADMIN_ALLOWED_EMAILS` に含まれている必要があります。 |

Local dev login helper:

```txt
GET /_local/admin/login
```

この helper は `APP_ENV=local` または `APP_ENV=testing` で、かつ `DIGESTPIPE_ADMIN_DEV_LOGIN_ENABLED=true` の場合だけ使えます。Google OAuth の production/admin authentication policy を置き換えるものではありません。
