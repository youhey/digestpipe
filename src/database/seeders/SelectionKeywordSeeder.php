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
            SelectionKeyword::query()->firstOrCreate(
                [
                    'type' => $keyword['type'],
                    'keyword' => $keyword['keyword'],
                ],
                $keyword,
            );
        }
    }

    /**
     * @return list<array{keyword: string, type: string, score: int, enabled: bool, locale: string, category: string|null, sort_order: int}>
     */
    private function keywords(): array
    {
        $keywords = [];
        $order = 10;

        foreach ($this->positiveKeywords() as $row) {
            [$keyword, $score, $locale, $category] = $row;
            $keywords[] = $this->row($keyword, 'positive', $score, $locale, $category, $order);
            $order += 10;
        }

        foreach ($this->negativeKeywords() as $row) {
            [$keyword, $score, $locale, $category] = $row;
            $keywords[] = $this->row($keyword, 'negative', $score, $locale, $category, $order);
            $order += 10;
        }

        return $keywords;
    }

    /**
     * @return list<array{0: string, 1: int, 2: string, 3: string|null}>
     */
    private function positiveKeywords(): array
    {
        return [
            ['PHP', 12, 'en', 'php'],
            ['Laravel', 15, 'en', 'laravel'],
            ['Composer', 10, 'en', 'php'],
            ['Symfony', 8, 'en', 'php'],
            ['PHPStan', 8, 'en', 'php'],
            ['PHP-CS-Fixer', 8, 'en', 'php'],
            ['Pest', 6, 'en', 'php'],
            ['PHPUnit', 6, 'en', 'php'],
            ['AWS', 12, 'en', 'aws'],
            ['Amazon Web Services', 12, 'en', 'aws'],
            ['EC2', 10, 'en', 'aws'],
            ['ECS', 12, 'en', 'aws'],
            ['Fargate', 10, 'en', 'aws'],
            ['Lambda', 10, 'en', 'aws'],
            ['RDS', 10, 'en', 'aws'],
            ['Aurora', 10, 'en', 'aws'],
            ['CloudFront', 10, 'en', 'aws'],
            ['S3', 8, 'en', 'aws'],
            ['IAM', 8, 'en', 'aws'],
            ['Route 53', 8, 'en', 'aws'],
            ['クラウド', 8, 'ja', 'aws'],
            ['Linux', 10, 'en', 'linux'],
            ['Bash', 8, 'en', 'linux'],
            ['Shell', 6, 'en', 'linux'],
            ['CLI', 8, 'en', 'linux'],
            ['systemd', 8, 'en', 'linux'],
            ['nginx', 8, 'en', 'linux'],
            ['Apache', 6, 'en', 'linux'],
            ['Ubuntu', 6, 'en', 'linux'],
            ['Debian', 6, 'en', 'linux'],
            ['Alpine Linux', 6, 'en', 'linux'],
            ['シェル', 6, 'ja', 'linux'],
            ['コマンドライン', 8, 'ja', 'linux'],
            ['MySQL', 12, 'en', 'database'],
            ['PostgreSQL', 10, 'en', 'database'],
            ['Postgres', 10, 'en', 'database'],
            ['SQLite', 8, 'en', 'database'],
            ['MariaDB', 6, 'en', 'database'],
            ['Redis', 6, 'en', 'database'],
            ['Valkey', 6, 'en', 'database'],
            ['ポスグレ', 8, 'ja', 'database'],
            ['データベース', 6, 'ja', 'database'],
            ['Docker', 12, 'en', 'container'],
            ['Docker Compose', 12, 'en', 'container'],
            ['Compose', 6, 'en', 'container'],
            ['container', 6, 'en', 'container'],
            ['containers', 6, 'en', 'container'],
            ['コンテナ', 8, 'ja', 'container'],
            ['homelab', 12, 'en', 'self-hosted'],
            ['home lab', 10, 'en', 'self-hosted'],
            ['self-hosted', 12, 'en', 'self-hosted'],
            ['self hosted', 12, 'en', 'self-hosted'],
            ['selfhosting', 10, 'en', 'self-hosted'],
            ['NAS', 8, 'en', 'self-hosted'],
            ['Proxmox', 8, 'en', 'self-hosted'],
            ['TrueNAS', 8, 'en', 'self-hosted'],
            ['ホームラボ', 10, 'ja', 'self-hosted'],
            ['セルフホスト', 12, 'ja', 'self-hosted'],
            ['自宅サーバー', 12, 'ja', 'self-hosted'],
            ['自宅サーバ', 12, 'ja', 'self-hosted'],
            ['IoT', 8, 'en', 'hardware'],
            ['Raspberry Pi', 10, 'en', 'hardware'],
            ['RasPi', 8, 'en', 'hardware'],
            ['ESP32', 10, 'en', 'hardware'],
            ['Arduino', 6, 'en', 'hardware'],
            ['microcontroller', 6, 'en', 'hardware'],
            ['embedded', 6, 'en', 'hardware'],
            ['ラズパイ', 10, 'ja', 'hardware'],
            ['ラズベリーパイ', 10, 'ja', 'hardware'],
            ['マイコン', 8, 'ja', 'hardware'],
            ['組み込み', 6, 'ja', 'hardware'],
            ['WebAssembly', 8, 'en', 'web'],
            ['WASM', 8, 'en', 'web'],
            ['Web API', 8, 'en', 'web'],
            ['REST API', 8, 'en', 'web'],
            ['HTTP API', 8, 'en', 'web'],
            ['OpenAPI', 6, 'en', 'web'],
            ['browser', 4, 'en', 'web'],
            ['browsers', 4, 'en', 'web'],
            ['ブラウザ', 4, 'ja', 'web'],
            ['API', 3, 'en', 'web'],
        ];
    }

    /**
     * @return list<array{0: string, 1: int, 2: string, 3: string|null}>
     */
    private function negativeKeywords(): array
    {
        return [
            ['crypto', -100, 'en', 'crypto'],
            ['cryptocurrency', -100, 'en', 'crypto'],
            ['bitcoin', -100, 'en', 'crypto'],
            ['ethereum', -100, 'en', 'crypto'],
            ['blockchain', -100, 'en', 'crypto'],
            ['web3', -100, 'en', 'crypto'],
            ['NFT', -100, 'en', 'crypto'],
            ['DAO', -80, 'en', 'crypto'],
            ['DeFi', -80, 'en', 'crypto'],
            ['tokenomics', -80, 'en', 'crypto'],
            ['ビットコイン', -100, 'ja', 'crypto'],
            ['仮想通貨', -100, 'ja', 'crypto'],
            ['暗号資産', -100, 'ja', 'crypto'],
            ['暗号通貨', -100, 'ja', 'crypto'],
            ['イーサリアム', -100, 'ja', 'crypto'],
            ['ブロックチェーン', -100, 'ja', 'crypto'],
            ['トークノミクス', -80, 'ja', 'crypto'],
            ['token', -10, 'en', 'crypto'],
            ['tokens', -10, 'en', 'crypto'],
            ['トークン', -10, 'ja', 'crypto'],
            ['VC', -20, 'en', 'startup'],
            ['venture capital', -30, 'en', 'startup'],
            ['startup funding', -40, 'en', 'startup'],
            ['seed round', -40, 'en', 'startup'],
            ['angel investor', -30, 'en', 'startup'],
            ['fundraising', -30, 'en', 'startup'],
            ['funding round', -40, 'en', 'startup'],
            ['Series A', -30, 'en', 'startup'],
            ['Series B', -30, 'en', 'startup'],
            ['monetization', -20, 'en', 'startup'],
            ['growth hacking', -30, 'en', 'startup'],
            ['スタートアップ', -20, 'ja', 'startup'],
            ['ベンチャー', -20, 'ja', 'startup'],
            ['ベンチャーキャピタル', -30, 'ja', 'startup'],
            ['資金調達', -40, 'ja', 'startup'],
            ['シードラウンド', -40, 'ja', 'startup'],
            ['エンジェル投資', -30, 'ja', 'startup'],
            ['投資家', -20, 'ja', 'startup'],
            ['マネタイズ', -20, 'ja', 'startup'],
            ['グロースハック', -30, 'ja', 'startup'],
            ['グロースハッキング', -30, 'ja', 'startup'],
        ];
    }

    /**
     * @return array{keyword: string, type: string, score: int, enabled: bool, locale: string, category: string|null, sort_order: int}
     */
    private function row(string $keyword, string $type, int $score, string $locale, ?string $category, int $sortOrder): array
    {
        return [
            'keyword' => $keyword,
            'type' => $type,
            'score' => $score,
            'enabled' => true,
            'locale' => $locale,
            'category' => $category,
            'sort_order' => $sortOrder,
        ];
    }
}
