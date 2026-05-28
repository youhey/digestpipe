<x-filament-widgets::widget>
    @once
        <style>
            .digestpipe-cloud-status-stack {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            .digestpipe-cloud-status-summary {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: space-between;
                gap: 0.75rem;
                min-width: 0;
                padding: 1rem;
                border: 1px solid rgb(229, 231, 235);
                border-radius: 0.75rem;
                background: rgb(249, 250, 251);
            }

            .dark .digestpipe-cloud-status-summary {
                border-color: rgba(255, 255, 255, 0.1);
                background: rgba(255, 255, 255, 0.05);
            }

            .digestpipe-cloud-status-summary-body {
                min-width: 0;
                flex: 1 1 20rem;
            }

            .digestpipe-cloud-status-kicker,
            .digestpipe-cloud-status-label {
                color: rgb(107, 114, 128);
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.025em;
                line-height: 1rem;
                text-transform: uppercase;
            }

            .dark .digestpipe-cloud-status-kicker,
            .dark .digestpipe-cloud-status-label {
                color: rgb(161, 161, 170);
            }

            .digestpipe-cloud-status-title {
                margin-top: 0.375rem;
                overflow: hidden;
                color: rgb(17, 24, 39);
                font-size: 0.875rem;
                font-weight: 600;
                line-height: 1.25rem;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .dark .digestpipe-cloud-status-title {
                color: rgb(255, 255, 255);
            }

            .digestpipe-cloud-status-commit {
                margin-top: 0.25rem;
                color: rgb(107, 114, 128);
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 0.75rem;
                line-height: 1rem;
            }

            .dark .digestpipe-cloud-status-commit {
                color: rgb(161, 161, 170);
            }

            .digestpipe-cloud-status-badge {
                display: inline-flex;
                align-items: center;
                padding: 0.25rem 0.625rem;
                border-radius: 0.5rem;
                font-size: 0.75rem;
                font-weight: 600;
                line-height: 1rem;
            }

            .digestpipe-cloud-status-badge-success {
                background: rgb(220, 252, 231);
                color: rgb(22, 101, 52);
            }

            .digestpipe-cloud-status-badge-danger {
                background: rgb(254, 226, 226);
                color: rgb(153, 27, 27);
            }

            .digestpipe-cloud-status-badge-warning {
                background: rgb(254, 249, 195);
                color: rgb(133, 77, 14);
            }

            .digestpipe-cloud-status-badge-neutral {
                background: rgb(243, 244, 246);
                color: rgb(55, 65, 81);
            }

            .dark .digestpipe-cloud-status-badge-success {
                background: rgba(34, 197, 94, 0.15);
                color: rgb(134, 239, 172);
            }

            .dark .digestpipe-cloud-status-badge-danger {
                background: rgba(239, 68, 68, 0.15);
                color: rgb(252, 165, 165);
            }

            .dark .digestpipe-cloud-status-badge-warning {
                background: rgba(234, 179, 8, 0.15);
                color: rgb(253, 224, 71);
            }

            .dark .digestpipe-cloud-status-badge-neutral {
                background: rgba(255, 255, 255, 0.08);
                color: rgb(212, 212, 216);
            }

            .digestpipe-cloud-status-grid {
                display: grid;
                grid-template-columns: repeat(1, minmax(0, 1fr));
                gap: 0.75rem;
            }

            @media (min-width: 768px) {
                .digestpipe-cloud-status-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1280px) {
                .digestpipe-cloud-status-grid {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
            }

            .digestpipe-cloud-status-item {
                min-width: 0;
                padding: 0.875rem 1rem;
                border: 1px solid rgb(229, 231, 235);
                border-radius: 0.75rem;
                background: rgb(255, 255, 255);
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            }

            .dark .digestpipe-cloud-status-item {
                border-color: rgba(255, 255, 255, 0.1);
                background: rgba(255, 255, 255, 0.05);
            }

            .digestpipe-cloud-status-value {
                display: -webkit-box;
                margin-top: 0.375rem;
                overflow: hidden;
                overflow-wrap: anywhere;
                color: rgb(17, 24, 39);
                font-size: 0.875rem;
                font-weight: 500;
                line-height: 1.25rem;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 3;
            }

            .digestpipe-cloud-status-value-mono {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            }

            .dark .digestpipe-cloud-status-value {
                color: rgb(255, 255, 255);
            }
        </style>
    @endonce

    <x-filament::section
        description="Laravel Cloud deployment status"
        heading="Cloud Status"
    >
        @if (! $status->configured)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Laravel Cloud API is not configured.
            </p>
        @elseif (! $status->available)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $status->errorMessage ?? 'Laravel Cloud deployment status is not available.' }}
            </p>
        @else
            @php
                $statusLabel = str($status->status)->after('deployment.')->headline()->toString();
                $statusBadgeClass = match (true) {
                    str_contains($status->status, 'succeeded'), str_contains($status->status, 'completed') => 'digestpipe-cloud-status-badge-success',
                    str_contains($status->status, 'failed'), str_contains($status->status, 'error') => 'digestpipe-cloud-status-badge-danger',
                    str_contains($status->status, 'running'), str_contains($status->status, 'building') => 'digestpipe-cloud-status-badge-warning',
                    default => 'digestpipe-cloud-status-badge-neutral',
                };
            @endphp

            <div class="digestpipe-cloud-status-stack">
                <div class="digestpipe-cloud-status-summary">
                    <div class="digestpipe-cloud-status-summary-body">
                        <div class="digestpipe-cloud-status-kicker">
                            Last deployment
                        </div>
                        <div class="digestpipe-cloud-status-title">
                            {{ $status->commitMessage ?? 'N/A' }}
                        </div>
                        <div class="digestpipe-cloud-status-commit">
                            {{ $status->commitHash ? str($status->commitHash)->limit(12, '') : 'N/A' }}
                        </div>
                    </div>
                    <span class="digestpipe-cloud-status-badge {{ $statusBadgeClass }}">
                        {{ $statusLabel }}
                    </span>
                </div>

                <dl class="digestpipe-cloud-status-grid">
                    @foreach ($rows as $row)
                        @if ($row['label'] !== 'Status')
                            <div class="digestpipe-cloud-status-item">
                                <dt class="digestpipe-cloud-status-label">
                                    {{ $row['label'] }}
                                </dt>
                                <dd @class([
                                    'digestpipe-cloud-status-value',
                                    'digestpipe-cloud-status-value-mono' => $row['label'] === 'Commit',
                                ])>
                                    {{ $row['value'] }}
                                </dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
