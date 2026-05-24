<?php

namespace App\Jobs;

use App\Analysis\ArticleAnalyzer;
use App\Models\NewsItem;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ニュース記事アイテムを元の言語のまま分析
 */
class AnalyzeNewsItemJob implements ShouldQueue
{
    use Queueable;

    /** @var int 分析対象のアイテム ID */
    public readonly int $newsItemId;

    /**
     * Constructor
     *
     * @param int $newsItemId
     */
    public function __construct(int $newsItemId)
    {
        $this->newsItemId = $newsItemId;
    }

    /**
     * ニュース記事アイテムを元の言語のまま分析して、構造化した分析結果 JSON を保存する
     *
     * @param ArticleAnalyzer $analyzer
     *
     * @return void
     */
    public function handle(ArticleAnalyzer $analyzer): void
    {
        Log::info('News item analysis job started.', [
            'news_item_id' => $this->newsItemId,
        ]);

        $item = NewsItem::query()->find($this->newsItemId);

        if (! $item instanceof NewsItem) {
            Log::warning('News item analysis job skipped missing item.', [
                'news_item_id' => $this->newsItemId,
            ]);

            return;
        }

        if ($item->analysis_status === 'completed') {
            Log::info('News item analysis job skipped completed item.', [
                'news_item_id' => $item->id,
            ]);

            return;
        }

        if ($this->hasUsableInput($item) === false) {
            $item->forceFill([
                'analysis_status' => 'skipped',
                'analysis_error' => 'Article analysis input was not usable.',
            ])->save();

            Log::info('News item analysis job skipped unusable input.', [
                'news_item_id' => $item->id,
                'article_content_status' => $item->article_content_status,
            ]);

            return;
        }

        try {
            $item->forceFill([
                'analysis_status' => 'processing',
                'analysis_error' => null,
            ])->save();

            $result = $analyzer->analyze($item);

            $item->forceFill([
                'analysis_status' => 'completed',
                'analysis_json' => $result->json,
                'analysis_model' => $result->model,
                'analysis_error' => null,
                'analyzed_at' => CarbonImmutable::now(),
            ])->save();

            Log::info('News item analysis job finished.', [
                'news_item_id' => $item->id,
                'analysis_status' => 'completed',
                'analysis_model' => $result->model,
            ]);
        } catch (Throwable $exception) {
            $item->forceFill([
                'analysis_status' => 'failed',
                'analysis_error' => self::shortErrorMessage($exception),
            ])->save();

            Log::error('News item analysis job failed.', [
                'news_item_id' => $item->id,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function hasUsableInput(NewsItem $item): bool
    {
        foreach ([$item->article_content_text, $item->excerpt, $item->title] as $value) {
            if (is_string($value) && trim(strip_tags($value)) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function shortErrorMessage(Throwable $exception): string
    {
        return substr($exception->getMessage(), 0, 500);
    }
}
