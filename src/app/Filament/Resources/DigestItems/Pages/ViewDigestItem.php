<?php

namespace App\Filament\Resources\DigestItems\Pages;

use App\Filament\Resources\DigestItems\DigestItemResource;
use App\Models\DigestItem;
use App\Translation\DigestItemTranslationService;
use App\Translation\TranslationException;
use App\Translation\TranslationResult;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use LogicException;

/**
 * Digest Item review 詳細画面
 */
class ViewDigestItem extends ViewRecord
{
    /** @var array<string, string> */
    public array $temporaryTranslations = [];

    /** @var array<string, bool> */
    public array $translationTruncation = [];

    protected static string $resource = DigestItemResource::class;

    /**
     * Article section の title を一時翻訳します。
     */
    public function translateArticle(): void
    {
        $record = $this->digestItem();

        if ($this->translateOne('article.title', $record->title)) {
            $this->translationSucceeded('Article title translated.');
        }
    }

    /**
     * Article content section の本文を一時翻訳します。
     */
    public function translateArticleContent(): void
    {
        $record = $this->digestItem();

        if ($this->translateOne('article_content.text', $record->article_content_text)) {
            $this->translationSucceeded('Article content translated.');
        }
    }

    /**
     * Analysis section の主要 text を一時翻訳します。
     */
    public function translateAnalysis(): void
    {
        $record = $this->digestItem();

        $translated = false;

        if ($this->translateOne('analysis.brief', DigestItemResource::analysisText($record, 'brief'), false)) {
            $translated = true;
        }

        if ($this->translateOne('analysis.detailed_summary', DigestItemResource::analysisText($record, 'detailed_summary'), false)) {
            $translated = true;
        }

        if ($this->translateOne('analysis.limitations', DigestItemResource::analysisText($record, 'limitations'), false)) {
            $translated = true;
        }

        if ($this->translateMany('analysis.key_points', $this->analysisListItems($record))) {
            $translated = true;
        }

        if ($translated) {
            $this->translationSucceeded('Analysis translated.');

            return;
        }

        if (! $this->hasTranslation('analysis.brief')
            && ! $this->hasTranslation('analysis.detailed_summary')
            && ! $this->hasTranslation('analysis.limitations')
            && ! $this->hasTranslation('analysis.key_points')) {
            $this->nothingToTranslate();
        }
    }

    public function translatedText(string $key): ?string
    {
        return $this->temporaryTranslations[$key] ?? null;
    }

    public function hasTranslation(string $key): bool
    {
        return isset($this->temporaryTranslations[$key]) && $this->temporaryTranslations[$key] !== '';
    }

    public function wasTranslationTruncated(string $key): bool
    {
        return $this->translationTruncation[$key] ?? false;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return DigestItemResource::manualRatingActions();
    }

    private function translateOne(string $key, ?string $text, bool $notifyEmpty = true): bool
    {
        try {
            $result = app(DigestItemTranslationService::class)->translateText($text);
        } catch (TranslationException $exception) {
            $this->translationFailed($exception);

            return false;
        }

        if ($result === null) {
            if ($notifyEmpty) {
                $this->nothingToTranslate();
            }

            return false;
        }

        $this->temporaryTranslations[$key] = $result->text;
        $this->translationTruncation[$key] = $result->truncated;

        return true;
    }

    /**
     * @param list<string> $texts
     */
    private function translateMany(string $key, array $texts): bool
    {
        try {
            $results = app(DigestItemTranslationService::class)->translateList($texts);
        } catch (TranslationException $exception) {
            $this->translationFailed($exception);

            return false;
        }

        if ($results === []) {
            return false;
        }

        $this->temporaryTranslations[$key] = implode("\n", array_map(
            static fn (TranslationResult $result): string => $result->text,
            $results,
        ));
        $this->translationTruncation[$key] = in_array(true, array_map(
            static fn (TranslationResult $result): bool => $result->truncated,
            $results,
        ), true);

        return true;
    }

    private function translationSucceeded(string $title): void
    {
        Notification::make()
            ->success()
            ->title($title)
            ->send();
    }

    private function translationFailed(TranslationException $exception): void
    {
        if ($exception->getMessage() === 'Translation is not configured.') {
            Notification::make()
                ->warning()
                ->title('Translation is not configured.')
                ->send();

            return;
        }

        Notification::make()
            ->danger()
            ->title('Translation failed. Please try again later.')
            ->send();
    }

    private function nothingToTranslate(): void
    {
        Notification::make()
            ->warning()
            ->title('Nothing to translate.')
            ->send();
    }

    private function digestItem(): DigestItem
    {
        $record = $this->getRecord();

        if (! $record instanceof DigestItem) {
            throw new LogicException('Digest Item record is required.');
        }

        return $record;
    }

    /**
     * @return list<string>
     */
    private function analysisListItems(DigestItem $record): array
    {
        $analysis = $record->analysis_json;

        if (! is_array($analysis)) {
            return [];
        }

        $content = $analysis['content'] ?? [];
        $value = $analysis['key_points'] ?? (is_array($content) ? ($content['key_points'] ?? null) : null);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && trim($item) !== '' && trim($item) !== 'N/A',
        ));
    }
}
