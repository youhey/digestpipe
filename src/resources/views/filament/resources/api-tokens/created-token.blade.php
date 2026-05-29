@if ($plainTextToken)
    <div
        x-data="{ copied: false }"
        class="mb-6 rounded-lg border border-warning-300 bg-warning-50 p-4 shadow-sm dark:border-warning-700 dark:bg-warning-950"
    >
        <div class="flex flex-col gap-3">
            <div>
                <h2 class="text-base font-semibold text-warning-900 dark:text-warning-100">
                    New API token
                </h2>
                <p class="mt-1 text-sm text-warning-800 dark:text-warning-200">
                    Copy this token now. It will not be shown again.
                </p>
                <p class="mt-1 text-sm text-warning-800 dark:text-warning-200">
                    {{ $tokenName }} for {{ $userEmail }}
                </p>
            </div>

            <textarea
                readonly
                rows="3"
                class="block w-full rounded-lg border-gray-300 bg-white font-mono text-sm text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            >{{ $plainTextToken }}</textarea>

            <div>
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg bg-warning-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-warning-500"
                    x-on:click="navigator.clipboard.writeText(@js($plainTextToken)); copied = true"
                >
                    Copy token
                </button>
                <span x-show="copied" x-cloak class="ml-3 text-sm text-warning-800 dark:text-warning-200">
                    Copied.
                </span>
            </div>
        </div>
    </div>
@endif
