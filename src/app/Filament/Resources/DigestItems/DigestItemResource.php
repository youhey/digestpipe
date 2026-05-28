<?php

namespace App\Filament\Resources\DigestItems;

use App\Filament\Resources\DigestItems\Pages\ListDigestItems;
use App\Filament\Resources\DigestItems\Pages\ViewDigestItem;
use App\Models\DigestItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Digest Item の human review を行う Filament resource
 */
class DigestItemResource extends Resource
{
    protected static ?string $model = DigestItem::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::DocumentText;

    protected static ?string $modelLabel = 'Digest Item';

    protected static ?string $pluralModelLabel = 'Digest Items';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'digest-items';

    /**
     * Digest Item review table を定義します。
     *
     * @param Table $table
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('source_key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(80),
                TextColumn::make('selection_score')
                    ->sortable(),
                TextColumn::make('selection_status')
                    ->sortable(),
                TextColumn::make('article_content_status')
                    ->label('content_status')
                    ->sortable(),
                TextColumn::make('analysis_status')
                    ->sortable(),
                TextColumn::make('content_type')
                    ->state(static fn (DigestItem $record): string => self::contentType($record) ?? 'N/A')
                    ->sortable(false),
                TextColumn::make('importance')
                    ->state(static fn (DigestItem $record): string => self::analysisValue($record, 'importance') ?? 'N/A')
                    ->sortable(false),
                TextColumn::make('confidence')
                    ->state(static fn (DigestItem $record): string => self::analysisValue($record, 'confidence') ?? 'N/A')
                    ->sortable(false),
                TextColumn::make('manual_rating')
                    ->state(static fn (DigestItem $record): string => self::manualRatingLabel($record))
                    ->sortable(),
            ])
            ->filters([
                Filter::make('ready_for_review')
                    ->label('Ready for Review')
                    ->default()
                    ->query(static fn (Builder $query): Builder => self::scopeReadyForReview($query)),
                Filter::make('selected')
                    ->query(static fn (Builder $query): Builder => self::scopeSelected($query)),
                Filter::make('skipped')
                    ->query(static fn (Builder $query): Builder => self::scopeSkipped($query)),
                Filter::make('unrated')
                    ->query(static fn (Builder $query): Builder => self::scopeUnrated($query)),
                Filter::make('rated_good')
                    ->label('Rated Good')
                    ->query(static fn (Builder $query): Builder => self::scopeRatedGood($query)),
                Filter::make('rated_bad')
                    ->label('Rated Bad')
                    ->query(static fn (Builder $query): Builder => self::scopeRatedBad($query)),
                Filter::make('content_fetched')
                    ->label('Content fetched')
                    ->query(static fn (Builder $query): Builder => self::scopeContentFetched($query)),
                Filter::make('analysis_completed')
                    ->label('Analysis completed')
                    ->query(static fn (Builder $query): Builder => self::scopeAnalysisCompleted($query)),
                SelectFilter::make('source_key')
                    ->label('Source')
                    ->options(fn (): array => self::sourceOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /**
     * Digest Item review view を定義します。
     *
     * @param Schema $schema
     *
     * @return Schema
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Article')
                    ->schema([
                        TextEntry::make('title')
                            ->columnSpanFull(),
                        TextEntry::make('source_key'),
                        TextEntry::make('source_url')
                            ->url(static fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                            ->placeholder('N/A')
                            ->columnSpanFull(),
                        TextEntry::make('discussion_url')
                            ->url(static fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                            ->placeholder('N/A')
                            ->columnSpanFull(),
                        TextEntry::make('published_at')
                            ->dateTime('Y-m-d H:i:s T'),
                        TextEntry::make('fetched_at')
                            ->dateTime('Y-m-d H:i:s T'),
                    ]),
                Section::make('Selection')
                    ->schema([
                        TextEntry::make('selection_score'),
                        TextEntry::make('selection_status')
                            ->badge(),
                        TextEntry::make('selection_reason')
                            ->placeholder('N/A')
                            ->columnSpanFull(),
                        TextEntry::make('matched_positive_keywords')
                            ->state(static fn (DigestItem $record): string => self::matchedKeywordsLabel($record, 'matched_good_keywords'))
                            ->columnSpanFull(),
                        TextEntry::make('matched_negative_keywords')
                            ->state(static fn (DigestItem $record): string => self::matchedKeywordsLabel($record, 'matched_bad_keywords'))
                            ->columnSpanFull(),
                    ]),
                Section::make('Article content')
                    ->schema([
                        TextEntry::make('article_content_status')
                            ->badge(),
                        TextEntry::make('article_content_text')
                            ->placeholder('N/A')
                            ->prose()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Analysis')
                    ->footerActions(self::manualRatingActions())
                    ->schema([
                        TextEntry::make('analysis_status')
                            ->badge(),
                        TextEntry::make('content_type')
                            ->state(static fn (DigestItem $record): string => self::contentType($record) ?? 'N/A'),
                        TextEntry::make('importance')
                            ->state(static fn (DigestItem $record): string => self::analysisValue($record, 'importance') ?? 'N/A'),
                        TextEntry::make('confidence')
                            ->state(static fn (DigestItem $record): string => self::analysisValue($record, 'confidence') ?? 'N/A'),
                        TextEntry::make('brief')
                            ->state(static fn (DigestItem $record): string => self::analysisText($record, 'brief') ?? 'N/A')
                            ->columnSpanFull(),
                        TextEntry::make('detailed_summary')
                            ->state(static fn (DigestItem $record): string => self::analysisText($record, 'detailed_summary') ?? 'N/A')
                            ->columnSpanFull(),
                        TextEntry::make('key_points')
                            ->state(static fn (DigestItem $record): string => self::analysisList($record, 'key_points'))
                            ->columnSpanFull(),
                        TextEntry::make('limitations')
                            ->state(static fn (DigestItem $record): string => self::analysisText($record, 'limitations') ?? 'N/A')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListDigestItems::route('/'),
            'view' => ViewDigestItem::route('/{record}'),
        ];
    }

    /**
     * @return array<Action>
     */
    public static function manualRatingActions(): array
    {
        $actions = [];

        for ($rating = 1; $rating <= 5; ++$rating) {
            $actions[] = self::starRatingAction($rating);
        }

        $actions[] = self::badRatingAction();

        return $actions;
    }

    /**
     * Ready for Review の条件を適用します。
     *
     * @param Builder<DigestItem> $query
     *
     * @return Builder<DigestItem>
     */
    public static function scopeReadyForReview(Builder $query): Builder
    {
        return $query
            ->where('selection_status', 'selected')
            ->where('article_content_status', 'completed')
            ->where('analysis_status', 'completed');
    }

    /**
     * @param Builder<DigestItem> $query
     *
     * @return Builder<DigestItem>
     */
    public static function scopeSelected(Builder $query): Builder
    {
        return $query->where('selection_status', 'selected');
    }

    /**
     * @param Builder<DigestItem> $query
     *
     * @return Builder<DigestItem>
     */
    public static function scopeSkipped(Builder $query): Builder
    {
        return $query->where('selection_status', 'skipped');
    }

    /**
     * @param Builder<DigestItem> $query
     *
     * @return Builder<DigestItem>
     */
    public static function scopeUnrated(Builder $query): Builder
    {
        return $query->where('manual_rating', null);
    }

    /**
     * @param Builder<DigestItem> $query
     *
     * @return Builder<DigestItem>
     */
    public static function scopeRatedGood(Builder $query): Builder
    {
        return $query
            ->where('manual_rating', '>=', 1)
            ->where('manual_rating', '<=', 5);
    }

    /**
     * @param Builder<DigestItem> $query
     *
     * @return Builder<DigestItem>
     */
    public static function scopeRatedBad(Builder $query): Builder
    {
        return $query->where('manual_rating', -1);
    }

    /**
     * @param Builder<DigestItem> $query
     *
     * @return Builder<DigestItem>
     */
    public static function scopeContentFetched(Builder $query): Builder
    {
        return $query->where('article_content_status', 'completed');
    }

    /**
     * @param Builder<DigestItem> $query
     *
     * @return Builder<DigestItem>
     */
    public static function scopeAnalysisCompleted(Builder $query): Builder
    {
        return $query->where('analysis_status', 'completed');
    }

    public static function manualRatingLabel(DigestItem $record): string
    {
        return match ($record->manual_rating) {
            -1 => 'Bad',
            1 => 'Good ★☆☆☆☆',
            2 => 'Good ★★☆☆☆',
            3 => 'Good ★★★☆☆',
            4 => 'Good ★★★★☆',
            5 => 'Good ★★★★★',
            default => 'Unrated',
        };
    }

    public static function contentType(DigestItem $record): ?string
    {
        $classification = self::classification($record);
        $contentType = $classification['content_type'] ?? null;

        return is_string($contentType) && trim($contentType) !== '' ? trim($contentType) : null;
    }

    public static function analysisValue(DigestItem $record, string $key): ?string
    {
        $classification = self::classification($record);
        $value = $classification[$key] ?? null;

        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        return null;
    }

    public static function analysisText(DigestItem $record, string $key): ?string
    {
        $analysis = $record->analysis_json;

        if (! is_array($analysis)) {
            return null;
        }

        $value = $analysis[$key] ?? null;

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $content = $analysis['content'] ?? null;

        if (is_array($content)) {
            $value = $content[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    public static function analysisList(DigestItem $record, string $key): string
    {
        $analysis = $record->analysis_json;

        if (! is_array($analysis)) {
            return 'N/A';
        }

        $value = $analysis[$key] ?? null;

        if (! is_array($value)) {
            return 'N/A';
        }

        $items = array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && trim($item) !== ''));

        return $items === [] ? 'N/A' : implode("\n", $items);
    }

    public static function matchedKeywordsLabel(DigestItem $record, string $key): string
    {
        $selectionResult = $record->selection_result;

        if (! is_array($selectionResult)) {
            return 'N/A';
        }

        $keywords = $selectionResult[$key] ?? null;

        if (! is_array($keywords)) {
            return 'N/A';
        }

        $items = array_values(array_filter($keywords, static fn (mixed $keyword): bool => is_string($keyword) && trim($keyword) !== ''));

        return $items === [] ? 'N/A' : implode(', ', $items);
    }

    private static function starRatingAction(int $rating): Action
    {
        return Action::make('rate_good_' . $rating)
            ->label('Good ' . $rating . ' star')
            ->hiddenLabel()
            ->tooltip('Good ' . $rating . ' star')
            ->icon(static fn (DigestItem $record): Heroicon => ($record->manual_rating ?? 0) >= $rating ? Heroicon::Star : Heroicon::OutlinedStar)
            ->color(static fn (DigestItem $record): string => ($record->manual_rating ?? 0) >= $rating ? 'warning' : 'gray')
            ->iconButton()
            ->action(static function (DigestItem $record) use ($rating): void {
                self::toggleManualRating($record, $rating);
            });
    }

    private static function badRatingAction(): Action
    {
        return Action::make('rate_bad')
            ->label('Bad')
            ->hiddenLabel()
            ->tooltip('Bad')
            ->icon(static fn (DigestItem $record): Heroicon => $record->manual_rating === -1 ? Heroicon::HandThumbDown : Heroicon::OutlinedHandThumbDown)
            ->color('gray')
            ->iconButton()
            ->action(static function (DigestItem $record): void {
                self::toggleManualRating($record, -1);
            });
    }

    private static function toggleManualRating(DigestItem $record, int $rating): void
    {
        if ($record->manual_rating === $rating) {
            $record->clearManualRating();
            $title = 'Manual rating をクリアしました。';
        } else {
            $record->setManualRating($rating);
            $title = 'Manual rating を保存しました。';
        }

        $record->save();

        Notification::make()
            ->success()
            ->title($title)
            ->send();
    }

    /**
     * @return array<string, string>
     */
    private static function sourceOptions(): array
    {
        $options = [];

        foreach (DB::table('digest_items')->select('source_key')->distinct()->orderBy('source_key')->pluck('source_key')->all() as $sourceKey) {
            if (is_string($sourceKey) && $sourceKey !== '') {
                $options[$sourceKey] = $sourceKey;
            }
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private static function classification(DigestItem $record): array
    {
        $analysis = $record->analysis_json;

        if (! is_array($analysis)) {
            return [];
        }

        $classification = $analysis['classification'] ?? null;

        if (! is_array($classification)) {
            return [];
        }

        $values = [];
        foreach ($classification as $key => $value) {
            if (is_string($key)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }
}
