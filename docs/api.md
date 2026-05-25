# API

digestpipe の HTTP API は private / read-only です。

現在の API は、完了済みの記事分析結果を downstream application へ渡すための Article JSON API だけを提供します。write API、public API、OAuth、login API、registration API、password reset flow は実装していません。

## Authentication

API authentication には Laravel Sanctum personal access token を使用します。

Client は token を `Authorization: Bearer` header で送信します。

```bash
curl -H "Authorization: Bearer ${DIGESTPIPE_API_TOKEN}" \
  "http://localhost:8080/api/articles"
```

API users と tokens は Artisan command で管理します。

Create an API user and token:

```bash
php artisan digestpipe:users:create-api-user user@example.test --name="DigestPipe User"
```

Rotate the API token:

```bash
php artisan digestpipe:users:rotate-api-token user@example.test
```

Token behavior:

- Tokens are shown only once when created or rotated.
- Store tokens securely outside the repository.
- Rotating a token invalidates the previous `digestpipe-api` token.
- Tokens should have the `digests:read` ability.
- Raw tokens are not stored on the `users` table.

## Endpoints

```txt
GET /api/articles
GET /api/articles/{id}
```

Both endpoints require:

```txt
auth:sanctum
abilities:digests:read
```

## GET /api/articles

Returns completed article analysis records.

Rules:

- Returns only records where `analysis_status=completed` and `analysis_json` is present.
- Default time window is the last 24 hours.
- Time filtering uses `published_at` with `fetched_at` fallback.
- Default limit is `100`.
- Maximum limit is `500`.
- Newest records are returned first.
- Raw `article_content_text` is not returned.
- Selection details are limited to `selection.status` and `selection.score`.

Supported query parameters:

```txt
from
to
source
limit
```

Examples:

```bash
curl -H "Authorization: Bearer ${DIGESTPIPE_API_TOKEN}" \
  "http://localhost:8080/api/articles"
```

```bash
curl -H "Authorization: Bearer ${DIGESTPIPE_API_TOKEN}" \
  "http://localhost:8080/api/articles?from=2026-05-24T00:00:00Z&to=2026-05-24T12:00:00Z"
```

```bash
curl -H "Authorization: Bearer ${DIGESTPIPE_API_TOKEN}" \
  "http://localhost:8080/api/articles?source=hacker_news&limit=50"
```

Response example:

```json
{
  "data": [
    {
      "id": 123,
      "source": {
        "key": "hacker_news",
        "name": "Hacker News",
        "feed_url": "https://news.ycombinator.com/rss"
      },
      "article": {
        "title": "Original title",
        "url": "https://example.com/article",
        "discussion_url": "https://news.ycombinator.com/item?id=123",
        "published_at": "2026-05-24T00:00:00.000000Z",
        "fetched_at": "2026-05-24T00:01:00.000000Z"
      },
      "selection": {
        "status": "selected",
        "score": 12
      },
      "analysis": {
        "schema_version": "1.0",
        "source_language": "en",
        "title": {
          "original": "Original title",
          "normalized": "Normalized title"
        },
        "content": {
          "brief": "Brief explanation.",
          "detailed_summary": "Detailed source-language summary.",
          "key_points": [
            "Important point"
          ],
          "background": null,
          "why_it_matters": "Why this item matters.",
          "limitations": null
        },
        "classification": {
          "content_type": "news_article",
          "topics": [
            "technology"
          ],
          "entities": [
            "Example Entity"
          ],
          "importance": 3,
          "confidence": 0.8
        }
      },
      "processing": {
        "analysis_model": "gpt-test",
        "analyzed_at": "2026-05-24T00:02:00.000000Z"
      }
    }
  ],
  "meta": {
    "count": 1,
    "limit": 100,
    "from": "2026-05-23T00:00:00.000000Z",
    "to": "2026-05-24T00:00:00.000000Z"
  }
}
```

## GET /api/articles/{id}

Returns one completed article analysis record.

Rules:

- Requires authentication.
- Uses the same item shape as `GET /api/articles`.
- Returns `404` when the item does not exist.
- Returns `404` when the item exists but is not API-visible because analysis is incomplete or missing.
- Raw `article_content_text` is not returned.
- Selection details are limited to `selection.status` and `selection.score`.

Example:

```bash
curl -H "Authorization: Bearer ${DIGESTPIPE_API_TOKEN}" \
  "http://localhost:8080/api/articles/123"
```

Response example:

```json
{
  "data": {
    "id": 123,
    "source": {
      "key": "hacker_news",
      "name": "Hacker News",
      "feed_url": "https://news.ycombinator.com/rss"
    },
    "article": {
      "title": "Original title",
      "url": "https://example.com/article",
      "discussion_url": "https://news.ycombinator.com/item?id=123",
      "published_at": "2026-05-24T00:00:00.000000Z",
      "fetched_at": "2026-05-24T00:01:00.000000Z"
    },
    "selection": {
      "status": "selected",
      "score": 12
    },
    "analysis": {
      "schema_version": "1.0",
      "source_language": "en",
      "title": {
        "original": "Original title",
        "normalized": "Normalized title"
      },
      "content": {
        "brief": "Brief explanation.",
        "detailed_summary": "Detailed source-language summary.",
        "key_points": [
          "Important point"
        ],
        "background": null,
        "why_it_matters": "Why this item matters.",
        "limitations": null
      },
      "classification": {
        "content_type": "news_article",
        "topics": [
          "technology"
        ],
        "entities": [
          "Example Entity"
        ],
        "importance": 3,
        "confidence": 0.8
      }
    },
    "processing": {
      "analysis_model": "gpt-test",
      "analyzed_at": "2026-05-24T00:02:00.000000Z"
    }
  }
}
```

## Error Responses

`401 Unauthorized`

Returned when the request does not include a valid Bearer token.

`403 Forbidden`

Returned when the token is valid but does not have the required `digests:read` ability.

`404 Not Found`

Returned when an article ID does not exist, or when the item exists but is not API-visible because analysis is incomplete or missing.

`422 Unprocessable Entity`

Returned when query parameters are invalid. Examples:

- `from` or `to` is not a valid timestamp.
- `from` is later than `to`.
- `limit` is not an integer.
- `limit` is less than `1` or greater than `500`.

## Current Limitations

- No public API.
- No write API.
- No OAuth.
- No login API.
- No registration API.
- No password reset.
- No pagination yet.
- No `fields` filtering yet.
- No topic filtering yet.
- Raw article content is not exposed by default.
- Internal selection results, including matched keywords, are not exposed.
- Source-specific metadata is not implemented yet.
