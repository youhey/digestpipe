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
 * News itemの翻訳処理をqueue worker上で実行します。
 */
class TranslateNewsItemJob implements ShouldQueue
{
    use Queueable;

    /** 翻訳対象のnews item IDです。 */
    public readonly int $newsItemId;

    /**
     * 翻訳jobを作成します。
     */
    public function __construct(int $newsItemId)
    {
        $this->newsItemId = $newsItemId;
    }

    /**
     * News itemを翻訳し、translation statusと翻訳結果を保存します。
     */
    public function handle(NewsAiProcessor $processor): void
    {
        Log::info('News item translation job started.', [
            'news_item_id' => $this->newsItemId,
        ]);

        $item = NewsItem::query()->find($this->newsItemId);

        if (! $item instanceof NewsItem) {
            Log::warning('News item translation job skipped missing item.', [
                'news_item_id' => $this->newsItemId,
            ]);

            return;
        }

        if ($item->translation_status === 'completed') {
            Log::info('News item translation job skipped completed item.', [
                'news_item_id' => $item->id,
            ]);

            return;
        }

        try {
            $item->forceFill([
                'translation_status' => 'processing',
                'translation_started_at' => CarbonImmutable::now(),
                'processing_error' => null,
            ])->save();

            Log::info('News item translation status changed.', [
                'news_item_id' => $item->id,
                'translation_status' => 'processing',
            ]);

            $result = $processor->translate($item);

            $item->forceFill([
                'translated_title' => $result->title,
                'translated_description' => $result->description,
                'translation_status' => 'completed',
                'translation_completed_at' => CarbonImmutable::now(),
                'processing_error' => null,
            ])->save();

            Log::info('News item translation job finished.', [
                'news_item_id' => $item->id,
                'translation_status' => 'completed',
            ]);
        } catch (Throwable $exception) {
            $item->forceFill([
                'translation_status' => 'failed',
                'processing_error' => self::shortErrorMessage($exception),
            ])->save();

            Log::error('News item translation job failed.', [
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
