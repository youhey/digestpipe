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
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($rows as $row)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            {{ $row['label'] }}
                        </div>
                        <div class="mt-1 line-clamp-2 break-words text-sm font-medium text-gray-950 dark:text-white">
                            {{ $row['value'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
