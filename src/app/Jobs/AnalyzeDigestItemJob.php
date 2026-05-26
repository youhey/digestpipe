<?php

namespace App\Jobs;

use App\Analysis\ArticleAnalyzer;
use App\Models\DigestItem;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Digest Itemを元の言語のまま分析
 */
class AnalyzeDigestItemJob implements ShouldQueue
{
    use Queueable;

    /** @var int 分析対象のアイテム ID */
    public readonly int $digestItemId;

    /**
     * Constructor
     *
     * @param int $digestItemId
     */
    public function __construct(int $digestItemId)
    {
        $this->digestItemId = $digestItemId;
    }

    /**
     * Digest Itemを元の言語のまま分析して、構造化した分析結果 JSON を保存する
     *
     * @param ArticleAnalyzer $analyzer
     *
     * @return void
     */
    public function handle(ArticleAnalyzer $analyzer): void
    {
        Log::info('Digest item analysis job started.', [
            'digest_item_id' => $this->digestItemId,
        ]);

        $item = DigestItem::query()->find($this->digestItemId);

        if (! $item instanceof DigestItem) {
            Log::warning('Digest item analysis job skipped missing item.', [
                'digest_item_id' => $this->digestItemId,
            ]);

            return;
        }

        if ($item->analysis_status === 'completed') {
            Log::info('Digest item analysis job skipped completed item.', [
                'digest_item_id' => $item->id,
            ]);

            return;
        }

        if ($this->hasUsableInput($item) === false) {
            $item->forceFill([
                'analysis_status' => 'skipped',
                'analysis_error' => 'Article analysis input was not usable.',
            ])->save();

            Log::info('Digest item analysis job skipped unusable input.', [
                'digest_item_id' => $item->id,
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

            Log::info('Digest item analysis job finished.', [
                'digest_item_id' => $item->id,
                'analysis_status' => 'completed',
                'analysis_model' => $result->model,
            ]);
        } catch (Throwable $exception) {
            $item->forceFill([
                'analysis_status' => 'failed',
                'analysis_error' => self::shortErrorMessage($exception),
            ])->save();

            Log::error('Digest item analysis job failed.', [
                'digest_item_id' => $item->id,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function hasUsableInput(DigestItem $item): bool
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
