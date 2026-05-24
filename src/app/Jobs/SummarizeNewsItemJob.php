<?php

namespace App\Jobs;

use App\Models\NewsItem;
use App\Processing\NewsAiProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 翻訳済みニュース記事アイテムを要約
 */
class SummarizeNewsItemJob implements ShouldQueue
{
    use Queueable;

    /** @var int 要約対象のアイテム ID */
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
     * 翻訳済みニュース記事アイテムを要約して DB に保存
     *
     * @param NewsAiProcessor $processor
     *
     * @return void
     */
    public function handle(NewsAiProcessor $processor): void
    {
        Log::info('News item summary job started.', [
            'news_item_id' => $this->newsItemId,
        ]);

        $item = NewsItem::query()->find($this->newsItemId);

        if (! $item instanceof NewsItem) {
            Log::warning('News item summary job skipped missing item.', [
                'news_item_id' => $this->newsItemId,
            ]);

            return;
        }

        if ($item->summary_status === 'completed') {
            Log::info('News item summary job skipped completed item.', [
                'news_item_id' => $item->id,
            ]);

            return;
        }

        if ($item->translation_status !== 'completed') {
            Log::info('News item summary job skipped untranslated item.', [
                'news_item_id' => $item->id,
                'translation_status' => $item->translation_status,
            ]);

            return;
        }

        try {
            $item->forceFill([
                'summary_status' => 'processing',
                'summary_started_at' => CarbonImmutable::now(),
                'processing_error' => null,
            ])->save();

            Log::info('News item summary status changed.', [
                'news_item_id' => $item->id,
                'summary_status' => 'processing',
            ]);

            $result = $processor->summarize($item);

            $item->forceFill([
                'summary' => $result->summary,
                'summary_status' => 'completed',
                'summary_completed_at' => CarbonImmutable::now(),
                'processing_error' => null,
            ])->save();

            Log::info('News item summary job finished.', [
                'news_item_id' => $item->id,
                'summary_status' => 'completed',
            ]);
        } catch (Throwable $exception) {
            $item->forceFill([
                'summary_status' => 'failed',
                'processing_error' => self::shortErrorMessage($exception),
            ])->save();

            Log::error('News item summary job failed.', [
                'news_item_id' => $item->id,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private static function shortErrorMessage(Throwable $exception): string
    {
        return substr($exception->getMessage(), 0, 500);
    }
}
