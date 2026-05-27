<?php

namespace App\Admin;

/**
 * 管理画面へのアクセスを許可するメールアドレス一覧を判定します。
 */
class AdminEmailAllowList
{
    /**
     * 指定されたメールアドレスが管理者 allow-list に含まれるかを返します。
     *
     * @param string|null $email
     *
     * @return bool
     */
    public function allows(?string $email): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        return in_array($this->normalize($email), $this->emails(), true);
    }

    /**
     * 設定済みの管理者メールアドレスを正規化して返します。
     *
     * @return list<string>
     */
    public function emails(): array
    {
        $configuredEmails = config('digestpipe.admin.allowed_emails', []);

        if (! is_array($configuredEmails)) {
            return [];
        }

        $emails = [];

        foreach ($configuredEmails as $configuredEmail) {
            if (! is_string($configuredEmail)) {
                continue;
            }

            $email = $this->normalize($configuredEmail);

            if ($email === '') {
                continue;
            }

            $emails[] = $email;
        }

        return array_values(array_unique($emails));
    }

    private function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
