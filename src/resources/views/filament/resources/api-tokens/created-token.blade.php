@if ($plainTextToken)
    <div
        x-data="{ open: true, copied: false }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 px-4 py-6"
        style="position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; padding: 1.5rem 1rem; background: rgb(3 7 18 / 0.55);"
        role="dialog"
        aria-modal="true"
        aria-labelledby="created-api-token-title"
    >
        <div
            class="w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            style="width: 100%; max-width: 48rem; overflow: hidden; border-radius: 0.75rem; background: var(--gray-900, #111827); color: #f9fafb; box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.55); border: 1px solid rgb(255 255 255 / 0.10);"
            x-on:click.outside="open = false"
        >
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800" style="border-bottom: 1px solid rgb(255 255 255 / 0.10); padding: 1rem 1.5rem;">
                <div class="flex items-start justify-between gap-4" style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                    <div>
                        <h2 id="created-api-token-title" class="text-lg font-semibold text-gray-950 dark:text-white" style="margin: 0; font-size: 1.125rem; font-weight: 600; color: #ffffff;">
                            New API token
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" style="margin: 0.25rem 0 0; font-size: 0.875rem; color: #d1d5db;">
                            Copy this token now. It will not be shown again.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                        style="border: 0; border-radius: 0.5rem; background: transparent; color: #9ca3af; cursor: pointer; font-size: 1.25rem; line-height: 1; padding: 0.5rem;"
                        x-on:click="open = false"
                        aria-label="Close"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>

            <div class="space-y-5 px-6 py-5" style="padding: 1.25rem 1.5rem;">
                <dl class="grid gap-4 sm:grid-cols-2" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin: 0 0 1.25rem;">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: #9ca3af;">
                            Token name
                        </dt>
                        <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white" style="margin: 0.25rem 0 0; font-size: 0.875rem; font-weight: 600; color: #ffffff;">
                            {{ $tokenName }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: #9ca3af;">
                            User
                        </dt>
                        <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white" style="margin: 0.25rem 0 0; font-size: 0.875rem; font-weight: 600; color: #ffffff;">
                            {{ $userEmail }}
                        </dd>
                    </div>
                </dl>

                <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 dark:border-warning-700 dark:bg-warning-950/30" style="border: 1px solid rgb(245 158 11 / 0.45); border-radius: 0.5rem; background: rgb(120 53 15 / 0.30); padding: 1rem;">
                    <p class="text-sm font-medium text-warning-900 dark:text-warning-100" style="margin: 0; font-size: 0.875rem; font-weight: 600; color: #fef3c7;">
                        Plain text token
                    </p>
                    <textarea
                        readonly
                        rows="4"
                        class="mt-3 block w-full resize-y rounded-lg border border-warning-200 bg-white p-3 font-mono text-sm leading-6 text-gray-950 shadow-sm focus:border-warning-500 focus:ring-warning-500 dark:border-warning-700 dark:bg-gray-950 dark:text-gray-100"
                        style="box-sizing: border-box; display: block; width: 100%; min-height: 6rem; margin-top: 0.75rem; resize: vertical; border: 1px solid rgb(245 158 11 / 0.45); border-radius: 0.5rem; background: #020617; color: #f9fafb; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace; font-size: 0.875rem; line-height: 1.5rem; padding: 0.75rem; box-shadow: inset 0 1px 2px rgb(0 0 0 / 0.25);"
                    >{{ $plainTextToken }}</textarea>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 sm:flex-row sm:justify-end dark:border-gray-800 dark:bg-gray-950/50" style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid rgb(255 255 255 / 0.10); background: rgb(3 7 18 / 0.45); padding: 1rem 1.5rem;">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                    style="display: inline-flex; align-items: center; justify-content: center; border: 1px solid #4b5563; border-radius: 0.5rem; background: #111827; color: #e5e7eb; cursor: pointer; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 1rem;"
                    x-on:click="open = false"
                >
                    Close
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-warning-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-warning-600 focus:outline-none focus:ring-2 focus:ring-warning-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                    style="display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 0.5rem; background: #f59e0b; color: #111827; cursor: pointer; font-size: 0.875rem; font-weight: 700; padding: 0.5rem 1rem; box-shadow: 0 1px 2px rgb(0 0 0 / 0.18);"
                    x-on:click="navigator.clipboard.writeText(@js($plainTextToken)); copied = true"
                >
                    Copy token
                </button>
                <span
                    x-show="copied"
                    x-cloak
                    class="self-center text-sm font-medium text-success-700 dark:text-success-400"
                    style="align-self: center; color: #4ade80; font-size: 0.875rem; font-weight: 600;"
                >
                    Copied.
                </span>
            </div>
        </div>
    </div>
@endif
