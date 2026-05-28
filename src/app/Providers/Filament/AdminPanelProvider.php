<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AnalysisInsights;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\FeedSources\FeedSourceResource;
use App\Filament\Resources\NegativeKeywords\NegativeKeywordResource;
use App\Filament\Resources\PositiveKeywords\PositiveKeywordResource;
use App\Filament\Widgets\AnalysisStatusChartWidget;
use App\Filament\Widgets\ArticleContentStatusChartWidget;
use App\Filament\Widgets\CloudStatusWidget;
use App\Filament\Widgets\PipelineHealthStatsOverviewWidget;
use App\Filament\Widgets\PipelineLatestActivityWidget;
use App\Filament\Widgets\RecentFailedDigestItemsWidget;
use App\Filament\Widgets\RecentSelectedDigestItemsWidget;
use App\Filament\Widgets\RecentSkippedDigestItemsWidget;
use App\Filament\Widgets\SelectionStatsOverviewWidget;
use App\Filament\Widgets\SelectionStatusChartWidget;
use App\Filament\Widgets\SourceSelectionBreakdownWidget;
use App\Filament\Widgets\TopNegativeKeywordsWidget;
use App\Filament\Widgets\TopPositiveKeywordsWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * digestpipe の private admin panel を定義します。
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Google OAuth のみで利用する管理画面 panel を設定します。
     *
     * @param Panel $panel
     *
     * @return Panel
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(fn () => redirect()->route('auth.google.redirect'))
            ->brandName('digestpipe')
            ->resources([
                FeedSourceResource::class,
                PositiveKeywordResource::class,
                NegativeKeywordResource::class,
            ])
            ->pages([
                Dashboard::class,
                AnalysisInsights::class,
            ])
            ->widgets([
                CloudStatusWidget::class,
                SelectionStatsOverviewWidget::class,
                SelectionStatusChartWidget::class,
                SourceSelectionBreakdownWidget::class,
                TopPositiveKeywordsWidget::class,
                TopNegativeKeywordsWidget::class,
                RecentSelectedDigestItemsWidget::class,
                RecentSkippedDigestItemsWidget::class,
                PipelineHealthStatsOverviewWidget::class,
                ArticleContentStatusChartWidget::class,
                AnalysisStatusChartWidget::class,
                PipelineLatestActivityWidget::class,
                RecentFailedDigestItemsWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
