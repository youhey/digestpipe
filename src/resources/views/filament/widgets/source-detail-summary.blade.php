<x-filament-widgets::widget>
    <x-filament::section
        description="Feed Source master data"
        heading="Source summary"
    >
        <dl class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                'key' => 'Key',
                'name' => 'Name',
                'url' => 'URL',
                'language' => 'Language',
                'enabled' => 'Enabled',
                'analysis_enabled' => 'Analysis enabled',
                'tier' => 'Tier',
                'category' => 'Category',
            ] as $field => $label)
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-1 break-words text-gray-950 dark:text-white">
                        @if (is_bool($source[$field] ?? null))
                            {{ $source[$field] ? 'true' : 'false' }}
                        @else
                            {{ $source[$field] ?? 'N/A' }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>
</x-filament-widgets::widget>
