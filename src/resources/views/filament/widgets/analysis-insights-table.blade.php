<x-filament-widgets::widget>
    <x-filament::section
        :description="$description"
        :heading="$heading"
    >
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5">
                        @foreach ($columns as $column)
                            <th class="px-3 py-2 text-start font-medium text-gray-700 dark:text-gray-200">
                                {{ $column }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($columns as $column)
                                <td class="max-w-md px-3 py-2 text-gray-950 dark:text-white">
                                    <span class="line-clamp-2 break-words">
                                        {{ $row[$column] ?? 'N/A' }}
                                    </span>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td
                                class="px-3 py-6 text-center text-gray-500 dark:text-gray-400"
                                colspan="{{ count($columns) }}"
                            >
                                {{ $emptyMessage }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
