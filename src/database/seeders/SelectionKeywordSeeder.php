<?php

namespace Database\Seeders;

use App\Models\SelectionKeyword;
use Illuminate\Database\Seeder;

/**
 * 初期 selection keyword を登録します。
 */
class SelectionKeywordSeeder extends Seeder
{
    /**
     * Seed the selection keywords.
     */
    public function run(): void
    {
        foreach ($this->keywords() as $keyword) {
            $selectionKeyword = SelectionKeyword::query()->firstOrNew([
                'type' => $keyword['type'],
                'keyword' => $keyword['keyword'],
            ]);

            if (! $selectionKeyword->exists) {
                $selectionKeyword->fill($keyword);
                $selectionKeyword->save();

                continue;
            }

            if (($selectionKeyword->match_mode ?? null) === null || $selectionKeyword->match_mode === '') {
                $selectionKeyword->forceFill([
                    'match_mode' => $keyword['match_mode'],
                ])->save();
            }
        }
    }

    /**
     * @return list<array{keyword: string, type: string, score: int, enabled: bool, locale: string, category: string|null, sort_order: int, match_mode: string}>
     */
    private function keywords(): array
    {
        $keywords = [];
        $order = 10;

        foreach ($this->positiveKeywords() as $row) {
            [$keyword, $score, $locale, $category, $matchMode] = $row;
            $keywords[] = $this->row($keyword, 'positive', $score, $locale, $category, $order, $matchMode);
            $order += 10;
        }

        foreach ($this->negativeKeywords() as $row) {
            [$keyword, $score, $locale, $category, $matchMode] = $row;
            $keywords[] = $this->row($keyword, 'negative', $score, $locale, $category, $order, $matchMode);
            $order += 10;
        }

        return $keywords;
    }

    /**
     * @return list<array{0: string, 1: int, 2: string, 3: string|null, 4: string}>
     */
    private function positiveKeywords(): array
    {
        return [
            ['PHP', 12, 'en', 'php', 'word_boundary'],
            ['Laravel', 15, 'en', 'laravel', 'word_boundary'],
            ['Composer', 10, 'en', 'php', 'word_boundary'],
            ['Symfony', 8, 'en', 'php', 'word_boundary'],
            ['PHPStan', 8, 'en', 'php', 'word_boundary'],
            ['PHP-CS-Fixer', 8, 'en', 'php', 'exact_phrase'],
            ['Pest', 6, 'en', 'php', 'word_boundary'],
            ['PHPUnit', 6, 'en', 'php', 'word_boundary'],
            ['Amazon Web Services', 12, 'en', 'aws', 'exact_phrase'],
            ['Amazon Linux', 8, 'en', 'aws', 'exact_phrase'],
            ['EC2', 10, 'en', 'aws', 'word_boundary'],
            ['ECS', 12, 'en', 'aws', 'word_boundary'],
            ['Fargate', 10, 'en', 'aws', 'word_boundary'],
            ['Lambda', 10, 'en', 'aws', 'word_boundary'],
            ['RDS', 10, 'en', 'aws', 'word_boundary'],
            ['Aurora', 10, 'en', 'aws', 'word_boundary'],
            ['CloudFront', 10, 'en', 'aws', 'word_boundary'],
            ['S3', 8, 'en', 'aws', 'word_boundary'],
            ['IAM', 8, 'en', 'aws', 'word_boundary'],
            ['Route 53', 8, 'en', 'aws', 'exact_phrase'],
            ['CloudWatch', 8, 'en', 'aws', 'word_boundary'],
            ['VPC', 8, 'en', 'aws', 'word_boundary'],
            ['ALB', 8, 'en', 'aws', 'word_boundary'],
            ['AWS EBS', 8, 'en', 'aws', 'exact_phrase'],
            ['AWS EFS', 8, 'en', 'aws', 'exact_phrase'],
            ['AWS SQS', 8, 'en', 'aws', 'exact_phrase'],
            ['AWS SNS', 8, 'en', 'aws', 'exact_phrase'],
            ['Step Functions', 8, 'en', 'aws', 'exact_phrase'],
            ['クラウド', 8, 'ja', 'aws', 'contains'],
            ['Linux', 10, 'en', 'linux', 'word_boundary'],
            ['Bash', 8, 'en', 'linux', 'word_boundary'],
            ['Shell', 6, 'en', 'linux', 'word_boundary'],
            ['CLI', 8, 'en', 'linux', 'word_boundary'],
            ['BPF', 8, 'en', 'linux', 'word_boundary'],
            ['eBPF', 10, 'en', 'linux', 'word_boundary'],
            ['systemd', 8, 'en', 'linux', 'word_boundary'],
            ['nginx', 8, 'en', 'linux', 'word_boundary'],
            ['Apache', 6, 'en', 'linux', 'word_boundary'],
            ['Ubuntu', 6, 'en', 'linux', 'word_boundary'],
            ['Debian', 6, 'en', 'linux', 'word_boundary'],
            ['Alpine Linux', 6, 'en', 'linux', 'exact_phrase'],
            ['シェル', 6, 'ja', 'linux', 'contains'],
            ['コマンドライン', 8, 'ja', 'linux', 'contains'],
            ['MySQL', 12, 'en', 'database', 'word_boundary'],
            ['PostgreSQL', 10, 'en', 'database', 'word_boundary'],
            ['Postgres', 10, 'en', 'database', 'word_boundary'],
            ['SQLite', 8, 'en', 'database', 'word_boundary'],
            ['MariaDB', 6, 'en', 'database', 'word_boundary'],
            ['SQL', 6, 'en', 'database', 'word_boundary'],
            ['Redis', 6, 'en', 'database', 'word_boundary'],
            ['Valkey', 6, 'en', 'database', 'word_boundary'],
            ['ポスグレ', 8, 'ja', 'database', 'contains'],
            ['データベース', 6, 'ja', 'database', 'contains'],
            ['Docker', 12, 'en', 'container', 'word_boundary'],
            ['Docker Compose', 12, 'en', 'container', 'exact_phrase'],
            ['Compose', 6, 'en', 'container', 'word_boundary'],
            ['container', 6, 'en', 'container', 'word_boundary'],
            ['containers', 6, 'en', 'container', 'word_boundary'],
            ['コンテナ', 8, 'ja', 'container', 'contains'],
            ['homelab', 12, 'en', 'self-hosted', 'word_boundary'],
            ['home lab', 10, 'en', 'self-hosted', 'exact_phrase'],
            ['self-hosted', 12, 'en', 'self-hosted', 'exact_phrase'],
            ['self hosted', 12, 'en', 'self-hosted', 'exact_phrase'],
            ['selfhosting', 10, 'en', 'self-hosted', 'word_boundary'],
            ['NAS', 8, 'en', 'self-hosted', 'word_boundary'],
            ['Proxmox', 8, 'en', 'self-hosted', 'word_boundary'],
            ['TrueNAS', 8, 'en', 'self-hosted', 'word_boundary'],
            ['ホームラボ', 10, 'ja', 'self-hosted', 'contains'],
            ['セルフホスト', 12, 'ja', 'self-hosted', 'contains'],
            ['自宅サーバー', 12, 'ja', 'self-hosted', 'contains'],
            ['自宅サーバ', 12, 'ja', 'self-hosted', 'contains'],
            ['IoT', 8, 'en', 'hardware', 'word_boundary'],
            ['Raspberry Pi', 10, 'en', 'hardware', 'exact_phrase'],
            ['RasPi', 8, 'en', 'hardware', 'word_boundary'],
            ['ESP32', 10, 'en', 'hardware', 'word_boundary'],
            ['Arduino', 6, 'en', 'hardware', 'word_boundary'],
            ['microcontroller', 6, 'en', 'hardware', 'word_boundary'],
            ['embedded', 6, 'en', 'hardware', 'word_boundary'],
            ['ラズパイ', 10, 'ja', 'hardware', 'contains'],
            ['ラズベリーパイ', 10, 'ja', 'hardware', 'contains'],
            ['マイコン', 8, 'ja', 'hardware', 'contains'],
            ['組み込み', 6, 'ja', 'hardware', 'contains'],
            ['Golang', 8, 'en', 'programming', 'word_boundary'],
            ['Rust', 8, 'en', 'programming', 'word_boundary'],
            ['Python', 6, 'en', 'programming', 'word_boundary'],
            ['WebAssembly', 8, 'en', 'web', 'word_boundary'],
            ['WASM', 8, 'en', 'web', 'word_boundary'],
            ['Web API', 8, 'en', 'web', 'exact_phrase'],
            ['REST API', 8, 'en', 'web', 'exact_phrase'],
            ['HTTP API', 8, 'en', 'web', 'exact_phrase'],
            ['OpenAPI', 6, 'en', 'web', 'word_boundary'],
            ['browser', 4, 'en', 'web', 'word_boundary'],
            ['browsers', 4, 'en', 'web', 'word_boundary'],
            ['ブラウザ', 4, 'ja', 'web', 'contains'],
            ['API', 3, 'en', 'web', 'word_boundary'],
            ['GitHub Actions', 12, 'en', 'devops', 'exact_phrase'],
            ['GitHub', 6, 'en', 'devops', 'word_boundary'],
            ['CI/CD', 8, 'en', 'devops', 'exact_phrase'],
            ['dependency', 6, 'en', 'devops', 'word_boundary'],
            ['dependencies', 6, 'en', 'devops', 'word_boundary'],
            ['CVE', 10, 'en', 'security', 'word_boundary'],
            ['vulnerability', 10, 'en', 'security', 'word_boundary'],
            ['security', 8, 'en', 'security', 'word_boundary'],
            ['FFmpeg', 6, 'en', 'tooling', 'word_boundary'],
            ['tmux', 6, 'en', 'tooling', 'word_boundary'],
            ['iTerm', 6, 'en', 'tooling', 'word_boundary'],
            ['vim', 6, 'en', 'tooling', 'word_boundary'],
            ['Neovim', 6, 'en', 'tooling', 'word_boundary'],
            ['emacs', 6, 'en', 'tooling', 'word_boundary'],
            ['Vscode', 6, 'en', 'tooling', 'word_boundary'],
            ['VS Code', 6, 'en', 'tooling', 'exact_phrase'],
            ['AGENTS.md', 10, 'en', 'agent', 'exact_phrase'],
            ['SKILL.md', 10, 'en', 'agent', 'exact_phrase'],
            ['SKILLS.md', 10, 'en', 'agent', 'exact_phrase'],
            ['Agent Skills', 10, 'en', 'agent', 'exact_phrase'],
            ['MCP', 8, 'en', 'agent', 'word_boundary'],
            ['Claude', 6, 'en', 'agent', 'word_boundary'],
            ['Codex', 8, 'en', 'agent', 'word_boundary'],
            ['Fastly', 6, 'en', 'cloud', 'word_boundary'],
            ['Cloudflare', 8, 'en', 'cloud', 'word_boundary'],
            ['Akamai', 6, 'en', 'cloud', 'word_boundary'],
            ['Laravel Cloud', 12, 'en', 'laravel', 'exact_phrase'],
            ['さくらインターネット', 8, 'ja', 'cloud', 'contains'],
        ];
    }

    /**
     * @return list<array{0: string, 1: int, 2: string, 3: string|null, 4: string}>
     */
    private function negativeKeywords(): array
    {
        return [
            ['crypto', -100, 'en', 'crypto', 'word_boundary'],
            ['cryptocurrency', -100, 'en', 'crypto', 'word_boundary'],
            ['bitcoin', -100, 'en', 'crypto', 'word_boundary'],
            ['ethereum', -100, 'en', 'crypto', 'word_boundary'],
            ['blockchain', -100, 'en', 'crypto', 'word_boundary'],
            ['web3', -100, 'en', 'crypto', 'word_boundary'],
            ['NFT', -100, 'en', 'crypto', 'word_boundary'],
            ['DAO', -80, 'en', 'crypto', 'word_boundary'],
            ['DeFi', -80, 'en', 'crypto', 'word_boundary'],
            ['tokenomics', -80, 'en', 'crypto', 'word_boundary'],
            ['ビットコイン', -100, 'ja', 'crypto', 'contains'],
            ['仮想通貨', -100, 'ja', 'crypto', 'contains'],
            ['暗号資産', -100, 'ja', 'crypto', 'contains'],
            ['暗号通貨', -100, 'ja', 'crypto', 'contains'],
            ['イーサリアム', -100, 'ja', 'crypto', 'contains'],
            ['ブロックチェーン', -100, 'ja', 'crypto', 'contains'],
            ['トークノミクス', -80, 'ja', 'crypto', 'contains'],
            ['crypto token', -60, 'en', 'crypto', 'exact_phrase'],
            ['governance token', -60, 'en', 'crypto', 'exact_phrase'],
            ['NFT token', -80, 'en', 'crypto', 'exact_phrase'],
            ['VC', -20, 'en', 'startup', 'word_boundary'],
            ['venture capital', -30, 'en', 'startup', 'exact_phrase'],
            ['startup funding', -40, 'en', 'startup', 'exact_phrase'],
            ['seed round', -40, 'en', 'startup', 'exact_phrase'],
            ['angel investor', -30, 'en', 'startup', 'exact_phrase'],
            ['fundraising', -30, 'en', 'startup', 'word_boundary'],
            ['funding round', -40, 'en', 'startup', 'exact_phrase'],
            ['Series A', -30, 'en', 'startup', 'exact_phrase'],
            ['Series B', -30, 'en', 'startup', 'exact_phrase'],
            ['monetization', -20, 'en', 'startup', 'word_boundary'],
            ['growth hacking', -30, 'en', 'startup', 'exact_phrase'],
            ['スタートアップ', -20, 'ja', 'startup', 'contains'],
            ['ベンチャー', -20, 'ja', 'startup', 'contains'],
            ['ベンチャーキャピタル', -30, 'ja', 'startup', 'contains'],
            ['資金調達', -40, 'ja', 'startup', 'contains'],
            ['シードラウンド', -40, 'ja', 'startup', 'contains'],
            ['エンジェル投資', -30, 'ja', 'startup', 'contains'],
            ['投資家', -20, 'ja', 'startup', 'contains'],
            ['マネタイズ', -20, 'ja', 'startup', 'contains'],
            ['グロースハック', -30, 'ja', 'startup', 'contains'],
            ['グロースハッキング', -30, 'ja', 'startup', 'contains'],
        ];
    }

    /**
     * @return array{keyword: string, type: string, score: int, enabled: bool, locale: string, category: string|null, sort_order: int, match_mode: string}
     */
    private function row(string $keyword, string $type, int $score, string $locale, ?string $category, int $sortOrder, string $matchMode): array
    {
        return [
            'keyword' => $keyword,
            'type' => $type,
            'score' => $score,
            'enabled' => true,
            'locale' => $locale,
            'category' => $category,
            'sort_order' => $sortOrder,
            'match_mode' => $matchMode,
        ];
    }
}
