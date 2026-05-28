<?php

namespace App\Filament\Widgets\SourceDetail;

use Filament\Widgets\Widget;

/**
 * Feed Source metadata を表示する source detail widget
 */
class SourceSummaryWidget extends Widget
{
    use SourceDetailWidget;

    protected string $view = 'filament.widgets.source-detail-summary';

    protected array|int|string $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'source' => $this->sourceReport()['source'],
        ];
    }
}
