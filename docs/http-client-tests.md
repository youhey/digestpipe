# PhpStorm HTTP Client Smoke Tests

The root `tests/http/` directory contains optional PhpStorm HTTP Client
scenarios for manual post-deployment and API-change verification.

These checks are broad and shallow integration smoke tests. They are not run by
`make test`, and they do not replace PHPUnit, Pest, or Laravel feature tests.

## Environment Files

Committed public values live in:

```txt
tests/http/http-client.env.json
```

Private values should be created locally from the example:

```bash
cp tests/http/http-client.private.env.json.example tests/http/http-client.private.env.json
```

Set `apiToken` for the environment you want to use. Set `articleId` only when
running the article detail request in `api.http`.

The real private environment file is ignored by Git:

```txt
tests/http/http-client.private.env.json
```

Do not commit API tokens or other secrets.

## Environments

Supported environments:

- `local`: `http://localhost:8080`
- `cloud`: `https://example.laravel.cloud`

Both environments define:

- `baseUrl`
- `defaultLimit`
- `from`
- `to`

The private environment file defines:

- `apiToken`
- `articleId`

## Scenarios

`tests/http/smoke.http` includes:

- `GET /` returns cacheable `404` and does not return the Laravel welcome page.
- `GET /up` returns `200` without authorization.
- `GET /api/articles` returns `401` without authorization.
- `GET /api/articles` returns JSON with a valid Bearer token.

`tests/http/api.http` includes:

- Articles index.
- Articles index with `limit=1`.
- Articles index with a date range.
- Article detail using `articleId`.
- Missing article detail returning `404`.

## Running

Open a `.http` file in PhpStorm, choose the `local` or `cloud` environment, and
run individual requests from the gutter.

Use `smoke.http` for a quick deployment check. Use `api.http` when verifying
the authenticated Article JSON API.

`/up` is the lightweight health and wake endpoint. `/api/articles` requires a
Sanctum personal access token with the `digests:read` ability.
