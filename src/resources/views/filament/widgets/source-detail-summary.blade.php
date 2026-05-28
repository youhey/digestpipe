<x-filament-widgets::widget>
    @once
        <style>
            .digestpipe-source-summary-grid {
                display: grid;
                grid-template-columns: repeat(1, minmax(0, 1fr));
                gap: 0.75rem;
            }

            @media (min-width: 768px) {
                .digestpipe-source-summary-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1280px) {
                .digestpipe-source-summary-grid {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
            }

            .digestpipe-source-summary-item {
                min-width: 0;
                padding: 0.875rem 1rem;
                border: 1px solid rgb(229, 231, 235);
                border-radius: 0.75rem;
                background: rgb(249, 250, 251);
            }

            .dark .digestpipe-source-summary-item {
                border-color: rgba(255, 255, 255, 0.1);
                background: rgba(255, 255, 255, 0.05);
            }

            .digestpipe-source-summary-label {
                color: rgb(107, 114, 128);
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.025em;
                line-height: 1rem;
                text-transform: uppercase;
            }

            .dark .digestpipe-source-summary-label {
                color: rgb(161, 161, 170);
            }

            .digestpipe-source-summary-value {
                margin-top: 0.375rem;
                overflow-wrap: anywhere;
                color: rgb(17, 24, 39);
                font-size: 0.875rem;
                font-weight: 500;
                line-height: 1.25rem;
            }

            .dark .digestpipe-source-summary-value {
                color: rgb(255, 255, 255);
            }

            .digestpipe-source-summary-badge {
                display: inline-flex;
                align-items: center;
                padding: 0.125rem 0.5rem;
                border-radius: 9999px;
                font-size: 0.75rem;
                font-weight: 600;
                line-height: 1rem;
            }

            .digestpipe-source-summary-badge-enabled {
                background: rgb(220, 252, 231);
                color: rgb(22, 101, 52);
            }

            .digestpipe-source-summary-badge-disabled {
                background: rgb(254, 226, 226);
                color: rgb(153, 27, 27);
            }

            .dark .digestpipe-source-summary-badge-enabled {
                background: rgba(34, 197, 94, 0.15);
                color: rgb(134, 239, 172);
            }

            .dark .digestpipe-source-summary-badge-disabled {
                background: rgba(239, 68, 68, 0.15);
                color: rgb(252, 165, 165);
            }
        </style>
    @endonce

    <x-filament::section
        description="Feed Source master data"
        heading="Source summary"
    >
        <dl class="digestpipe-source-summary-grid">
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
                <div class="digestpipe-source-summary-item">
                    <dt class="digestpipe-source-summary-label">{{ $label }}</dt>
                    <dd class="digestpipe-source-summary-value">
                        @if (is_bool($source[$field] ?? null))
                            <span @class([
                                'digestpipe-source-summary-badge',
                                'digestpipe-source-summary-badge-enabled' => $source[$field],
                                'digestpipe-source-summary-badge-disabled' => ! $source[$field],
                            ])>
                                {{ $source[$field] ? 'true' : 'false' }}
                            </span>
                        @else
                            {{ $source[$field] ?? 'N/A' }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>
</x-filament-widgets::widget>
