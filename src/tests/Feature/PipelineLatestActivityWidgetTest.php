<?php

namespace Tests\Feature;

use App\Filament\Widgets\PipelineLatestActivityWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * @internal
 */
class PipelineLatestActivityWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function testTimestampDisplayUsesCompactHumanReadableFormat(): void
    {
        config(['app.timezone' => 'UTC']);

        $value = $this->formattedTimestamp('2026-05-28T05:10:42.000000Z');

        self::assertSame('2026-05-28 05:10:42 UTC', $value);
    }

    private function formattedTimestamp(?string $value): string
    {
        $widget = app(PipelineLatestActivityWidget::class);
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('formattedTimestamp');
        $formatted = $method->invoke($widget, $value);

        self::assertIsString($formatted);

        return $formatted;
    }
}
