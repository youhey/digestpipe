<x-filament-widgets::widget>
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
                $statusColor = match (true) {
                    str_contains($status->status, 'succeeded'), str_contains($status->status, 'completed') => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30',
                    str_contains($status->status, 'failed'), str_contains($status->status, 'error') => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30',
                    str_contains($status->status, 'running'), str_contains($status->status, 'building') => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30',
                    default => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
                };
            @endphp

            <div class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3 rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            Last deployment
                        </div>
                        <div class="mt-1 truncate text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $status->commitMessage ?? 'N/A' }}
                        </div>
                        <div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ $status->commitHash ? str($status->commitHash)->limit(12, '') : 'N/A' }}
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColor }}">
                        {{ $statusLabel }}
                    </span>
                </div>

                <dl class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($rows as $row)
                        @if ($row['label'] !== 'Status')
                            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/5">
                                <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                    {{ $row['label'] }}
                                </dt>
                                <dd @class([
                                    'mt-1 break-words text-sm font-medium text-gray-950 dark:text-white',
                                    'font-mono' => $row['label'] === 'Commit',
                                    'line-clamp-3' => $row['label'] === 'Commit message' || $row['label'] === 'Failure reason',
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
