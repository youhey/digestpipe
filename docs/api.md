# API

digestpipe の HTTP API は private API です。

現在の API は、完了済みの記事分析結果を downstream application へ渡すための Article JSON API と、downstream から Article rating を返す Rating API だけを提供します。public API、OAuth、login API、registration API、password reset flow は実装していません。

## Authentication

API authentication には Laravel Sanctum personal access token を使用します。

Client は token を `Authorization: Bearer` header で送信します。

```bash
curl -H "Authorization: Bearer ${DIGESTPIPE_API_TOKEN}" \
  "http://localhost:8080/api/articles"
```

API users と tokens は Filament admin UI または Artisan command で管理します。

Filament admin UI:

- `/admin/api-tokens`
- `Create API Token` action で token を作成
- `Edit Token` action で既存 token の token name と abilities を編集
- `Revoke Token` / `Revoke All API Tokens` action で token を失効
- UI で作成する token の既定 ability は `digests:read`
- UI で選択できる ability は `digests:read` と `digests:rate`

Plain text token は作成直後に一度だけ表示されます。Token 一覧や edit modal では plain text token や token hash は表示しません。既存 token の metadata 編集では token 文字列を再発行しません。

Artisan commands:

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
- Read tokens should have the `digests:read` ability.
- Rating tokens should have the `digests:rate` ability.
- Raw tokens are not stored on the `users` table.

## Endpoints

The machine-readable OpenAPI contract is available at
[`docs/openapi.yaml`](openapi.yaml). Keep this document and the OpenAPI schema in
sync when the Article JSON API response shape changes.

```txt
GET /api/articles
GET /api/articles/{id}
PUT /api/articles/{id}/rating
DELETE /api/articles/{id}/rating
```

Read endpoints require:

```txt
auth:sanctum
abilities:digests:read
```

Rating endpoints require:

```txt
auth:sanctum
abilities:digests:rate
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
- Response wrapper is `articles`.
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
  "articles": [
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

## Selection

`selection` describes the deterministic keyword-based gate that runs before
article analysis.

Current shape:

```json
{
  "status": "selected",
  "score": 12
}
```

The API intentionally exposes only the public selection status and score.
Internal details such as matched keywords and selection reasons are not returned
by the Article JSON API.

### `selection.status`

Selection status is the current item-level selection state.

Current validation/storage:

- Type: `string`
- Enum: not enforced at the API layer

Known status values produced by the current pipeline:

```txt
pending
needs_content
selected
skipped
```

Meaning:

- `pending`: Selection has not been evaluated yet.
- `needs_content`: Pre-content selection did not hard-skip the item. Article
  content should be fetched before final selection.
- `selected`: The item passed final selection and may proceed to analysis.
- `skipped`: The item was filtered out by selection and should not proceed to
  analysis.

### `selection.score`

Selection score is the deterministic keyword score used by the selection gate.

Current validation/storage:

- Type: `integer|null`
- `null`: Selection has not produced a score yet.
- Fixed numeric range: not defined.

The score starts from the configured `default_score` and adds the configured
positive and negative keyword weights for keywords matched in the selection
input text.

Selection input differs by phase:

- Pre-content selection uses `title` and `excerpt`.
- Post-content selection uses `title`, `excerpt`, and `article_content_text`.

Current default thresholds:

```txt
analysis_threshold = 1
skip_threshold = -50
```

Current operational behavior:

- Before article content is fetched, items with
  `score <= skip_threshold` become `skipped`.
- Before article content is fetched, all other items become `needs_content`.
- After article content is fetched, items with
  `score >= analysis_threshold` become `selected`.
- After article content is fetched, items below `analysis_threshold` become
  `skipped`.

`selection.score` is not the same as `analysis.classification.importance`.
Selection score is a deterministic pre-analysis gate based on configured
keyword weights. `importance` is generated later by the article analysis model
and is exported as downstream metadata.

## Analysis Classification

`analysis.classification` is part of the stored `analysis_json`.

This object is generated by the article analysis pipeline and returned unchanged
by the API. digestpipe currently validates the data shape and value ranges, but
does not use these fields for API filtering, ranking, or processing decisions.
Downstream applications may use them for ranking, grouping, filtering,
translation, rewriting, narration, or personalization.

Current shape:

```json
{
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
```

### `content_type`

Content type is a source-language classification label for the analyzed item.

Current validation:

- Type: `string`
- Required: yes
- Empty string: not allowed
- Enum: not currently defined

Typical values may include labels such as `news_article`, `blog_post`,
`release_note`, `documentation`, `announcement`, or `discussion`, but the API
does not currently enforce this list.

### `topics`

Topics are broad subject labels that describe the item.

Current validation:

- Type: `array<string>`
- Required: yes
- Empty array: not allowed
- Empty string item: not allowed
- Enum: not currently defined

Typical values may include labels such as `technology`, `programming`, `php`,
`laravel`, `aws`, `linux`, `devops`, `web`, `hardware`, or `self-hosted`, but
the API does not currently enforce this list.

Topics are intended as coarse digest signals, not as a fully controlled
taxonomy. Downstream applications should treat unknown topic values as valid
strings.

### `entities`

Entities are named people, organizations, products, projects, services,
standards, or other concrete names mentioned in the article.

Current validation:

- Type: `array<string>`
- Required: yes
- Empty array: allowed
- Empty string item: not allowed
- Enum: not applicable

Entities are open-ended by design. They are not normalized to canonical IDs, and
the API does not currently deduplicate aliases such as `AWS` and
`Amazon Web Services`.

### `importance`

Importance is the model's estimate of how important the item is as a digest
signal.

Current validation:

- Type: `integer`
- Required: yes
- Range: `1` to `5`

Operational meaning:

```txt
1: Low importance. Narrow, minor, or mostly incidental.
2: Somewhat low importance. Potentially relevant, but not a priority item.
3: Normal importance. A reasonable default digest candidate.
4: High importance. Likely useful for technical, product, or operational awareness.
5: Very high importance. Broad impact, major release, incident, breaking change, or similarly significant item.
```

`importance` is not the same as `selection.score`. Selection score is a
deterministic RSS/title/excerpt/content keyword gate used before analysis.
`importance` is generated inside `analysis_json` after article analysis and is
currently exported only as metadata for downstream applications.

### `confidence`

Confidence is the model's estimate of how reliable the generated analysis is,
given the available input text.

Current validation:

- Type: `number`
- Required: yes
- Range: `0.0` to `1.0`

Operational meaning:

```txt
0.0-0.2: Very low confidence. Input was likely missing, weak, or not useful.
0.3-0.5: Low confidence. Some signal exists, but important context may be missing.
0.6-0.8: Normal confidence. Suitable for digest use, with some possible uncertainty.
0.9-1.0: High confidence. Input was sufficient and the analysis appears well grounded.
```

Low confidence should usually be read together with
`analysis.content.limitations`. For example, paywalls, cookie walls, weak
article extraction, short source text, or missing context should reduce
confidence and be described in `limitations` when possible.

## GET /api/articles/{id}

Returns one completed article analysis record.

Rules:

- Requires authentication.
- Uses the same item shape as `GET /api/articles`.
- Response wrapper is `article`.
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
  "article": {
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

## PUT /api/articles/{id}/rating

Sets or overwrites the downstream rating for one API-visible Article.

Rules:

- Requires `digests:rate`.
- Only records visible through `GET /api/articles/{id}` can be rated.
- Returns `404` when the item does not exist, analysis is incomplete, or `analysis_json` is missing.
- Request field is `rating`.
- Response wrapper is `article_rating`.
- Response uses `rating` and `rated_at`.
- Internal DB field names `manual_rating` and `manual_rated_at` are not exposed.
- `rating = -1` means Bad.
- `rating = 1..5` means Good star rating.
- `rating = 0`, `null`, missing values, and values outside `-1 | 1..5` are rejected.

Request example:

```json
{
  "rating": 5
}
```

Response example:

```json
{
  "article_rating": {
    "article_id": 123,
    "rating": 5,
    "rated_at": "2026-05-31T10:15:00.000000Z"
  }
}
```

## DELETE /api/articles/{id}/rating

Clears the downstream rating for one API-visible Article.

Rules:

- Requires `digests:rate`.
- Only records visible through `GET /api/articles/{id}` can have rating cleared.
- Returns `404` when the item does not exist, analysis is incomplete, or `analysis_json` is missing.
- Returns the updated rating state with `200 OK`; it does not return `204 No Content`.
- Internal DB field names `manual_rating` and `manual_rated_at` are not exposed.

Response example:

```json
{
  "article_rating": {
    "article_id": 123,
    "rating": null,
    "rated_at": null
  }
}
```

## Error Responses

`401 Unauthorized`

Returned when the request does not include a valid Bearer token.

`403 Forbidden`

Returned when the token is valid but does not have the required ability.

- Article read endpoints require `digests:read`.
- Article rating endpoints require `digests:rate`.

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
- No write API other than Article rating.
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
