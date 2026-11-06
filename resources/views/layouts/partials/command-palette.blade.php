<div class="command-palette" id="command-palette" hidden>
    <div class="command-backdrop" data-command-close></div>
    <div class="command-dialog" role="dialog" aria-modal="true" aria-labelledby="command-title">
        <h2 id="command-title" class="sr-only">{{ __('Command palette') }}</h2>

        <div class="command-input-row">
            <x-icon name="search" :size="18" class="command-search-icon" />
            <input
                type="text"
                id="command-input"
                class="command-input"
                placeholder="{{ __('Search pages, actions and settings…') }}"
                autocomplete="off"
                spellcheck="false"
                aria-controls="command-results"
                aria-autocomplete="list"
            >
            <button type="button" class="command-esc" data-command-close aria-label="{{ __('Close') }}">Esc</button>
        </div>

        <div class="command-results" id="command-results" role="listbox" aria-label="{{ __('Search results') }}"></div>

        <div class="command-footer">
            <span class="command-hint"><kbd>↑</kbd><kbd>↓</kbd> {{ __('to navigate') }}</span>
            <span class="command-hint"><kbd>↵</kbd> {{ __('to open') }}</span>
            <span class="command-hint"><kbd>Esc</kbd> {{ __('to close') }}</span>
        </div>
    </div>
</div>
