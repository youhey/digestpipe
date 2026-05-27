<?php

namespace Database\Seeders;

use App\Models\FeedSource;
use Illuminate\Database\Seeder;

/**
 * 初期 RSS フィード情報源を登録します。
 */
class FeedSourceSeeder extends Seeder
{
    /**
     * Seed the feed sources.
     */
    public function run(): void
    {
        foreach ($this->feedSources() as $feedSource) {
            FeedSource::query()->firstOrCreate(
                ['key' => $feedSource['key']],
                $feedSource,
            );
        }
    }

    /**
     * @return list<array{key: string, name: string, url: string, language: string, enabled: bool, analysis_enabled: bool, tier: string, category: string, sort_order: int}>
     */
    private function feedSources(): array
    {
        return [
            ['key' => 'hacker_news', 'name' => 'Hacker News', 'url' => 'https://news.ycombinator.com/rss', 'language' => 'en', 'enabled' => true, 'analysis_enabled' => true, 'tier' => 'core', 'category' => 'aggregator', 'sort_order' => 10],
            ['key' => 'lobsters_programming', 'name' => 'Lobsters / Programming', 'url' => 'https://lobste.rs/t/programming.rss', 'language' => 'en', 'enabled' => true, 'analysis_enabled' => true, 'tier' => 'core', 'category' => 'programming', 'sort_order' => 20],
            ['key' => 'lobsters_web', 'name' => 'Lobsters / Web', 'url' => 'https://lobste.rs/t/web.rss', 'language' => 'en', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'web', 'sort_order' => 30],
            ['key' => 'lobsters_linux', 'name' => 'Lobsters / Linux', 'url' => 'https://lobste.rs/t/linux.rss', 'language' => 'en', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'linux', 'sort_order' => 40],
            ['key' => 'lobsters_devops', 'name' => 'Lobsters / DevOps', 'url' => 'https://lobste.rs/t/devops.rss', 'language' => 'en', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'devops', 'sort_order' => 50],
            ['key' => 'php_weekly', 'name' => 'PHP Weekly', 'url' => 'https://www.phpweekly.com/feed', 'language' => 'en', 'enabled' => true, 'analysis_enabled' => true, 'tier' => 'core', 'category' => 'php', 'sort_order' => 60],
            ['key' => 'laravel_news', 'name' => 'Laravel News', 'url' => 'https://laravel-news.com/feed', 'language' => 'en', 'enabled' => true, 'analysis_enabled' => true, 'tier' => 'core', 'category' => 'laravel', 'sort_order' => 70],
            ['key' => 'stitcher_io', 'name' => 'Stitcher.io', 'url' => 'https://stitcher.io/feed', 'language' => 'en', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'php', 'sort_order' => 80],
            ['key' => 'php_internals_news', 'name' => 'PHP Internals News', 'url' => 'https://phpinternals.news/feed.rss', 'language' => 'en', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'php', 'sort_order' => 90],
            ['key' => 'php_watch', 'name' => 'PHP.Watch', 'url' => 'https://php.watch/feed', 'language' => 'en', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'php', 'sort_order' => 100],
            ['key' => 'aws_news', 'name' => 'AWS News', 'url' => 'https://aws.amazon.com/new/feed/', 'language' => 'en', 'enabled' => true, 'analysis_enabled' => true, 'tier' => 'core', 'category' => 'aws', 'sort_order' => 110],
            ['key' => 'zenn_php', 'name' => 'Zenn / PHP', 'url' => 'https://zenn.dev/topics/php/feed', 'language' => 'ja', 'enabled' => true, 'analysis_enabled' => true, 'tier' => 'core', 'category' => 'php', 'sort_order' => 120],
            ['key' => 'zenn_laravel', 'name' => 'Zenn / Laravel', 'url' => 'https://zenn.dev/topics/laravel/feed', 'language' => 'ja', 'enabled' => true, 'analysis_enabled' => true, 'tier' => 'core', 'category' => 'laravel', 'sort_order' => 130],
            ['key' => 'qiita_php', 'name' => 'Qiita / PHP', 'url' => 'https://qiita.com/tags/php/feed.atom', 'language' => 'ja', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'php', 'sort_order' => 140],
            ['key' => 'qiita_laravel', 'name' => 'Qiita / Laravel', 'url' => 'https://qiita.com/tags/laravel/feed.atom', 'language' => 'ja', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'laravel', 'sort_order' => 150],
            ['key' => 'hackaday', 'name' => 'Hackaday', 'url' => 'https://hackaday.com/blog/feed/', 'language' => 'en', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'hardware', 'sort_order' => 160],
            ['key' => 'selfh_st', 'name' => 'selfh.st', 'url' => 'https://selfh.st/rss/', 'language' => 'en', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'self-hosted', 'sort_order' => 170],
            ['key' => 'noted_lol', 'name' => 'Noted.lol', 'url' => 'https://noted.lol/rss', 'language' => 'en', 'enabled' => false, 'analysis_enabled' => false, 'tier' => 'candidate', 'category' => 'self-hosted', 'sort_order' => 180],
        ];
    }
}
