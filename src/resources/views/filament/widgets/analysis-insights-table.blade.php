<x-filament-widgets::widget>
    @once
        <style>
            .digestpipe-insights-table-wrapper {
                overflow: hidden;
                border: 1px solid rgba(209, 213, 219, 1);
                border-radius: 0.75rem;
                background: rgb(255, 255, 255);
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            }

            .dark .digestpipe-insights-table-wrapper {
                border-color: rgba(255, 255, 255, 0.1);
                background: rgb(24, 24, 27);
            }

            .digestpipe-insights-table-scroll {
                overflow-x: auto;
            }

            .digestpipe-insights-table {
                min-width: 100%;
                border-collapse: collapse;
                font-size: 0.875rem;
                line-height: 1.25rem;
                text-align: left;
            }

            .digestpipe-insights-table th {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid rgba(209, 213, 219, 1);
                background: rgb(249, 250, 251);
                color: rgb(107, 114, 128);
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.025em;
                text-transform: uppercase;
                white-space: nowrap;
            }

            .dark .digestpipe-insights-table th {
                border-bottom-color: rgba(255, 255, 255, 0.1);
                background: rgba(255, 255, 255, 0.05);
                color: rgb(161, 161, 170);
            }

            .digestpipe-insights-table td {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid rgba(229, 231, 235, 1);
                color: rgb(17, 24, 39);
                vertical-align: top;
            }

            .dark .digestpipe-insights-table td {
                border-bottom-color: rgba(255, 255, 255, 0.1);
                color: rgb(255, 255, 255);
            }

            .digestpipe-insights-table tbody tr:hover td {
                background: rgb(249, 250, 251);
            }

            .dark .digestpipe-insights-table tbody tr:hover td {
                background: rgba(255, 255, 255, 0.05);
            }

            .digestpipe-insights-table .numeric {
                text-align: right;
                font-variant-numeric: tabular-nums;
            }

            .digestpipe-insights-table .wide-cell {
                display: -webkit-box;
                min-width: 20rem;
                max-width: 36rem;
                overflow: hidden;
                overflow-wrap: anywhere;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
            }

            .digestpipe-insights-table .nowrap-cell {
                white-space: nowrap;
            }

            .digestpipe-insights-table .sort-button {
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
                color: inherit;
            }

            .digestpipe-insights-table .sort-button:hover {
                color: rgb(55, 65, 81);
            }

            .dark .digestpipe-insights-table .sort-button:hover {
                color: rgb(229, 231, 235);
            }

            .digestpipe-insights-table .sort-direction {
                color: rgb(156, 163, 175);
                font-size: 0.625rem;
                font-weight: 400;
                text-transform: none;
            }

            .digestpipe-insights-table .empty-row {
                padding: 2rem 1rem;
                color: rgb(107, 114, 128);
                text-align: center;
            }

            .dark .digestpipe-insights-table .empty-row {
                color: rgb(161, 161, 170);
            }
        </style>
    @endonce

    <x-filament::section
        :description="$description"
        :heading="$heading"
    >
        @php
            $numericColumns = ['id', 'count', 'score', 'confidence', 'importance'];
            $wideColumns = ['title', 'limitations'];
        @endphp

        <div
            class="digestpipe-insights-table-wrapper"
            x-data="{
                sortColumn: null,
                sortDirection: 'asc',
                sort(index, numeric) {
                    this.sortDirection = this.sortColumn === index && this.sortDirection === 'asc' ? 'desc' : 'asc'
                    this.sortColumn = index

                    const rows = Array.from(this.$refs.body.querySelectorAll('tr[data-row]'))
                    const direction = this.sortDirection === 'asc' ? 1 : -1

                    rows.sort((left, right) => {
                        const leftValue = left.children[index]?.dataset.sortValue ?? ''
                        const rightValue = right.children[index]?.dataset.sortValue ?? ''

                        if (numeric) {
                            const leftNumber = Number.parseFloat(leftValue)
                            const rightNumber = Number.parseFloat(rightValue)

                            if (Number.isFinite(leftNumber) && Number.isFinite(rightNumber)) {
                                return (leftNumber - rightNumber) * direction
                            }
                        }

                        return leftValue.localeCompare(rightValue, undefined, { numeric: true }) * direction
                    })

                    rows.forEach((row) => this.$refs.body.appendChild(row))
                },
            }"
        >
            <div class="digestpipe-insights-table-scroll">
                <table class="digestpipe-insights-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th
                                    scope="col"
                                    @class([
                                        'numeric' => in_array($column, $numericColumns, true),
                                    ])
                                >
                                    <button
                                        class="sort-button"
                                        type="button"
                                        x-on:click="sort({{ $loop->index }}, {{ in_array($column, $numericColumns, true) ? 'true' : 'false' }})"
                                    >
                                        <span>{{ $column }}</span>
                                        <span
                                            class="sort-direction"
                                            x-show="sortColumn === {{ $loop->index }}"
                                            x-text="sortDirection"
                                        ></span>
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody
                        x-ref="body"
                    >
                        @forelse ($rows as $row)
                            <tr
                                class="transition hover:bg-gray-50 dark:hover:bg-white/5"
                                data-row
                            >
                                @foreach ($columns as $column)
                                    <td
                                        data-sort-value="{{ $row[$column] ?? '' }}"
                                        @class([
                                            'numeric' => in_array($column, $numericColumns, true),
                                        ])
                                    >
                                        <span @class([
                                            'wide-cell' => in_array($column, $wideColumns, true),
                                            'nowrap-cell' => ! in_array($column, $wideColumns, true),
                                        ])>
                                            {{ $row[$column] ?? 'N/A' }}
                                        </span>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td
                                    class="empty-row"
                                    colspan="{{ count($columns) }}"
                                >
                                    {{ $emptyMessage }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
