@props(['name', 'title', 'maxWidth' => '520px'])

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:keydown.escape.window="open = false"
>
    <template x-teleport="body">
        <div
            class="modal-backdrop"
            x-show="open"
            x-cloak
            x-transition.opacity
            @click.self="open = false"
            role="dialog"
            aria-modal="true"
        >
            <div class="modal" style="max-width: {{ $maxWidth }}">
                <div class="modal-header">
                    <h3 class="modal-title">{{ $title }}</h3>
                    <button type="button" class="btn-icon" @click="open = false" aria-label="Close dialog">
                        <x-icon name="x" :size="17" />
                    </button>
                </div>
                <div class="modal-body">{{ $slot }}</div>
                @isset($footer)
                    <div class="modal-footer">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </template>
</div>
