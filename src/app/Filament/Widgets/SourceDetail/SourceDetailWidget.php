<?php

namespace App\Filament\Widgets\SourceDetail;

use App\Admin\SourceDetailQuery;

/**
 * Source Detail widgets が共有する report accessor
 *
 * @phpstan-import-type SourceDetailReport from SourceDetailQuery
 */
trait SourceDetailWidget
{
    /** @var string */
    public string $sourceKey = '';

    /**
     * @return SourceDetailReport
     */
    protected function sourceReport(): array
    {
        return app(SourceDetailQuery::class)->reportForSourceKey($this->sourceKey);
    }
}
