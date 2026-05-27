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

## 現在の範囲

この foundation では domain resource は未実装です。

今後の候補:

- feed sources
- selection keywords
- selection thresholds
- source metadata
- read-only Digest Item operational views
- analysis/content type reports
