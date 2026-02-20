<div>
    @if(!$isStarted)
        <div style="padding: 1.5rem;">
            <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 p-4 border border-gray-200 dark:border-white/10"
                style="padding: 1.5rem; border-radius: 0.75rem;">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2"
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div class="space-y-2">
                        <label class="text-sm font-medium leading-6 text-gray-950 dark:text-white"
                            style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <svg style="width: 1.25rem; height: 1.25rem; color: #6b7280;" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            Date From
                        </label>
                        <input type="datetime-local" wire:model="dateFrom"
                            class="block w-full rounded-lg border-0 bg-white py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-500"
                            style="width: 100%; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #d1d5db; color-scheme: dark; accent-color: #d97706;">
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium leading-6 text-gray-950 dark:text-white"
                            style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <svg style="width: 1.25rem; height: 1.25rem; color: #6b7280;" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            Date To
                        </label>
                        <input type="datetime-local" wire:model="dateTo"
                            class="block w-full rounded-lg border-0 bg-white py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-500"
                            style="width: 100%; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #d1d5db; color-scheme: dark; accent-color: #d97706;">
                    </div>
                </div>

                <div class="mt-4 space-y-2" style="margin-top: 1.5rem;">
                    <label class="text-sm font-medium leading-6 text-gray-950 dark:text-white"
                        style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <svg style="width: 1.25rem; height: 1.25rem; color: #6b7280;" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6 13.5V3.75m0 9.75a1.5 1.5 0 010 3m0-3a1.5 1.5 0 000 3m0 3.75V16.5m12-3V3.75m0 9.75a1.5 1.5 0 010 3m0-3a1.5 1.5 0 000 3m0 3.75V16.5m-6-9V3.75m0 3.75a1.5 1.5 0 010 3m0-3a1.5 1.5 0 000 3m0 9.75V10.5" />
                        </svg>
                        Limit (Items)
                    </label>
                    <input type="number" wire:model="limit" placeholder="e.g. 50 (empty for no limit)"
                        class="block w-full rounded-lg border-0 bg-white py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-500"
                        style="width: 100%; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #d1d5db;">
                </div>
            </div>

            <div class="flex justify-end pt-2" style="display: flex; justify-content: flex-end; padding-top: 1rem;">
                <button type="button" wire:click="startParsing" wire:loading.attr="disabled"
                    style="display: inline-flex; align-items: center; justify-content: center; background-color: #d97706; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; gap: 0.5rem;"
                    class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-primary-600 text-white hover:bg-primary-500 focus-visible:ring-primary-500/50 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus-visible:ring-primary-400/50 w-full sm:w-auto">
                    <svg style="width: 1.25rem; height: 1.25rem;" wire:loading.remove fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                    </svg>
                    <svg style="width: 1.25rem; height: 1.25rem;" wire:loading class="animate-spin -ml-1 mr-3 text-white"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span wire:loading.remove>Start Parsing</span>
                    <span wire:loading>Processing...</span>
                </button>
            </div>
        </div>
    @else
        <div class="rounded-lg overflow-hidden border border-gray-700 shadow-xl"
            style="border-radius: 0.5rem; overflow: hidden; border: 1px solid #374151; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <div class="bg-gray-800 px-4 py-2 flex items-center gap-2 border-b border-gray-700"
                style="background-color: #1f2937; padding: 0.5rem 1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid #374151;">
                <div class="w-3 h-3 rounded-full bg-red-500"
                    style="width: 0.75rem; height: 0.75rem; border-radius: 9999px; background-color: #ef4444;"></div>
                <div class="w-3 h-3 rounded-full bg-yellow-500"
                    style="width: 0.75rem; height: 0.75rem; border-radius: 9999px; background-color: #eab308;"></div>
                <div class="w-3 h-3 rounded-full bg-green-500"
                    style="width: 0.75rem; height: 0.75rem; border-radius: 9999px; background-color: #22c55e;"></div>
                <span class="ml-2 text-xs text-gray-400 font-mono"
                    style="margin-left: 0.5rem; font-size: 0.75rem; color: #9ca3af; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">news-crawler.log</span>
            </div>
            <div wire:poll.1s="updateLog"
                class="p-4 bg-gray-950 text-green-400 font-mono text-xs overflow-y-auto h-96 whitespace-pre-wrap"
                style="padding: 1rem; background-color: #030712; color: #4ade80; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.75rem; overflow-y: auto; height: 24rem; white-space: pre-wrap; text-align: left;">
                {{ $output }}
            </div>
        </div>
    @endif
</div>