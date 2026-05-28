<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('selection_keywords', function (Blueprint $table): void {
            $table->string('match_mode', 32)->default('contains')->after('sort_order');
        });

        foreach ($this->defaultKeywordMatchModes() as $row) {
            DB::table('selection_keywords')
                ->where('type', $row['type'])
                ->where('keyword', $row['keyword'])
                ->update(['match_mode' => $row['match_mode']]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('selection_keywords', function (Blueprint $table): void {
            $table->dropColumn('match_mode');
        });
    }

    /**
     * @return list<array{type: string, keyword: string, match_mode: string}>
     */
    private function defaultKeywordMatchModes(): array
    {
        return [
            ...$this->rows('positive', [
                ['PHP', 'word_boundary'],
                ['Laravel', 'word_boundary'],
                ['Composer', 'word_boundary'],
                ['Symfony', 'word_boundary'],
                ['PHPStan', 'word_boundary'],
                ['PHP-CS-Fixer', 'exact_phrase'],
                ['Pest', 'word_boundary'],
                ['PHPUnit', 'word_boundary'],
                ['AWS', 'word_boundary'],
                ['Amazon Web Services', 'exact_phrase'],
                ['EC2', 'word_boundary'],
                ['ECS', 'word_boundary'],
                ['Fargate', 'word_boundary'],
                ['Lambda', 'word_boundary'],
                ['RDS', 'word_boundary'],
                ['Aurora', 'word_boundary'],
                ['CloudFront', 'word_boundary'],
                ['S3', 'word_boundary'],
                ['IAM', 'word_boundary'],
                ['Route 53', 'exact_phrase'],
                ['クラウド', 'contains'],
                ['Linux', 'word_boundary'],
                ['Bash', 'word_boundary'],
                ['Shell', 'word_boundary'],
                ['CLI', 'word_boundary'],
                ['systemd', 'word_boundary'],
                ['nginx', 'word_boundary'],
                ['Apache', 'word_boundary'],
                ['Ubuntu', 'word_boundary'],
                ['Debian', 'word_boundary'],
                ['Alpine Linux', 'exact_phrase'],
                ['シェル', 'contains'],
                ['コマンドライン', 'contains'],
                ['MySQL', 'word_boundary'],
                ['PostgreSQL', 'word_boundary'],
                ['Postgres', 'word_boundary'],
                ['SQLite', 'word_boundary'],
                ['MariaDB', 'word_boundary'],
                ['Redis', 'word_boundary'],
                ['Valkey', 'word_boundary'],
                ['ポスグレ', 'contains'],
                ['データベース', 'contains'],
                ['Docker', 'word_boundary'],
                ['Docker Compose', 'exact_phrase'],
                ['Compose', 'word_boundary'],
                ['container', 'word_boundary'],
                ['containers', 'word_boundary'],
                ['コンテナ', 'contains'],
                ['homelab', 'word_boundary'],
                ['home lab', 'exact_phrase'],
                ['self-hosted', 'exact_phrase'],
                ['self hosted', 'exact_phrase'],
                ['selfhosting', 'word_boundary'],
                ['NAS', 'word_boundary'],
                ['Proxmox', 'word_boundary'],
                ['TrueNAS', 'word_boundary'],
                ['ホームラボ', 'contains'],
                ['セルフホスト', 'contains'],
                ['自宅サーバー', 'contains'],
                ['自宅サーバ', 'contains'],
                ['IoT', 'word_boundary'],
                ['Raspberry Pi', 'exact_phrase'],
                ['RasPi', 'word_boundary'],
                ['ESP32', 'word_boundary'],
                ['Arduino', 'word_boundary'],
                ['microcontroller', 'word_boundary'],
                ['embedded', 'word_boundary'],
                ['ラズパイ', 'contains'],
                ['ラズベリーパイ', 'contains'],
                ['マイコン', 'contains'],
                ['組み込み', 'contains'],
                ['WebAssembly', 'word_boundary'],
                ['WASM', 'word_boundary'],
                ['Web API', 'exact_phrase'],
                ['REST API', 'exact_phrase'],
                ['HTTP API', 'exact_phrase'],
                ['OpenAPI', 'word_boundary'],
                ['browser', 'word_boundary'],
                ['browsers', 'word_boundary'],
                ['ブラウザ', 'contains'],
                ['API', 'word_boundary'],
            ]),
            ...$this->rows('negative', [
                ['crypto', 'word_boundary'],
                ['cryptocurrency', 'word_boundary'],
                ['bitcoin', 'word_boundary'],
                ['ethereum', 'word_boundary'],
                ['blockchain', 'word_boundary'],
                ['web3', 'word_boundary'],
                ['NFT', 'word_boundary'],
                ['DAO', 'word_boundary'],
                ['DeFi', 'word_boundary'],
                ['tokenomics', 'word_boundary'],
                ['ビットコイン', 'contains'],
                ['仮想通貨', 'contains'],
                ['暗号資産', 'contains'],
                ['暗号通貨', 'contains'],
                ['イーサリアム', 'contains'],
                ['ブロックチェーン', 'contains'],
                ['トークノミクス', 'contains'],
                ['token', 'word_boundary'],
                ['tokens', 'word_boundary'],
                ['トークン', 'contains'],
                ['VC', 'word_boundary'],
                ['venture capital', 'exact_phrase'],
                ['startup funding', 'exact_phrase'],
                ['seed round', 'exact_phrase'],
                ['angel investor', 'exact_phrase'],
                ['fundraising', 'word_boundary'],
                ['funding round', 'exact_phrase'],
                ['Series A', 'exact_phrase'],
                ['Series B', 'exact_phrase'],
                ['monetization', 'word_boundary'],
                ['growth hacking', 'exact_phrase'],
                ['スタートアップ', 'contains'],
                ['ベンチャー', 'contains'],
                ['ベンチャーキャピタル', 'contains'],
                ['資金調達', 'contains'],
                ['シードラウンド', 'contains'],
                ['エンジェル投資', 'contains'],
                ['投資家', 'contains'],
                ['マネタイズ', 'contains'],
                ['グロースハック', 'contains'],
                ['グロースハッキング', 'contains'],
            ]),
        ];
    }

    /**
     * @param list<array{0: string, 1: string}> $keywords
     *
     * @return list<array{type: string, keyword: string, match_mode: string}>
     */
    private function rows(string $type, array $keywords): array
    {
        return array_map(static fn (array $row): array => [
            'type' => $type,
            'keyword' => $row[0],
            'match_mode' => $row[1],
        ], $keywords);
    }
};
