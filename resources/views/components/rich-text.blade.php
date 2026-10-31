@props([
    'name' => 'body',
    'label' => 'Body',
    'value' => null,
    'help' => null,
    'hint' => 'Drag an image by a corner to resize it. Use “Show HTML code” to edit the markup side by side.',
    'uploadRoute' => null,
    'height' => 480,
    'formatField' => true,
    'formatName' => null,
])

@php
    // Only one rich editor is rendered per form. The primary body field keeps
    // the original short ids so existing admin styles keep working.
    $key = $name === 'body' ? '' : '-'.Str::slug($name);
    $editorId = 'rich-body'.$key;
    $shellId = 'rich-shell'.$key;
    $codePaneId = 'rich-code-pane'.$key;
    $codeId = 'rich-code'.$key;
    $toggleId = 'rich-code-toggle'.$key;
    $applyId = 'rich-code-apply'.$key;
    $formatField = $formatName ?? $name.'_format';
@endphp

@once
    @push('head')
        <link rel="stylesheet" href="{{ asset('vendor/jodit/jodit.min.css') }}">
        <style>
            /* Rich text editor shell */
            .rich-shell { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; }
            .rich-shell.is-split { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
            @media (max-width: 1100px) { .rich-shell.is-split { grid-template-columns: minmax(0, 1fr); } }

            .rich-pane { min-width: 0; }
            .rich-code-pane { display: flex; flex-direction: column; min-width: 0; }
            .rich-code-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
            .rich-code-head strong { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted, #667085); }
            .rich-code-pane textarea { width: 100%; height: 480px; padding: 12px; font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace; font-size: 12px; line-height: 1.55; tab-size: 2; color: #e2e8f0; background: #0f172a; border: 1px solid #1e293b; border-radius: 10px; resize: vertical; white-space: pre; overflow: auto; }
            .rich-tools { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
            .rich-tools .field-hint { margin: 0; }

            /* Blend Jodit with the admin theme */
            .rich-pane .jodit-container { border-radius: 10px; border-color: var(--border, #cfd8e5); }
            .rich-pane .jodit-workplace { min-height: 340px; }
            .rich-pane .jodit-wysiwyg { color: #1f2a44; background: #fff; font-size: 15px; line-height: 1.7; }
            .rich-pane .jodit-wysiwyg h2 { font-size: 24px; margin: 18px 0 8px; }
            .rich-pane .jodit-wysiwyg h3 { font-size: 19px; margin: 16px 0 8px; }
            .rich-pane .jodit-wysiwyg blockquote { border-left: 4px solid #cbd5e1; margin-left: 0; padding-left: 14px; color: #475467; }
            .rich-pane .jodit-wysiwyg img { max-width: 100%; }
            .rich-pane .jodit-toolbar__box { border-radius: 10px 10px 0 0; }
        </style>
    @endpush
@endonce

<div {{ $attributes }}>
    <label class="field-label" for="{{ $editorId }}">{{ $label }}</label>
    @if ($formatField)
        <input type="hidden" name="{{ $formatField }}" value="html">
    @endif

    <div class="rich-shell" id="{{ $shellId }}">
        <div class="rich-pane">
            <textarea id="{{ $editorId }}" name="{{ $name }}" rows="16" aria-label="{{ $label }}">{{ old($name, $value) }}</textarea>
        </div>
        <div class="rich-pane rich-code-pane" id="{{ $codePaneId }}" hidden>
            <div class="rich-code-head">
                <strong>HTML code</strong>
                <button type="button" class="btn btn-secondary btn-sm" id="{{ $applyId }}">Apply to editor</button>
            </div>
            <textarea id="{{ $codeId }}" spellcheck="false" wrap="off" aria-label="HTML source code"></textarea>
        </div>
    </div>

    <div class="rich-tools">
        <button type="button" class="btn btn-secondary btn-sm" id="{{ $toggleId }}" aria-expanded="false">
            <x-icon name="code" :size="14" /> <span>Show HTML code</span>
        </button>
        @if ($hint)
            <span class="field-hint">{{ $hint }}</span>
        @endif
    </div>

    @error($name)
        <span class="field-error"><x-icon name="alert-circle" :size="11" />{{ $message }}</span>
    @enderror
    @if ($help)
        <span class="field-hint">{{ $help }}</span>
    @endif
    <p class="cell-sub" style="margin-top:6px">Scripts, event handlers and unsafe URLs or CSS are removed when saved.</p>
</div>

@push('scripts')
    <script src="{{ asset('vendor/jodit/jodit.min.js') }}"></script>
    <script>
        (() => {
            const textarea = document.getElementById(@json($editorId));
            const shell = document.getElementById(@json($shellId));
            const codePane = document.getElementById(@json($codePaneId));
            const code = document.getElementById(@json($codeId));
            const toggle = document.getElementById(@json($toggleId));
            const apply = document.getElementById(@json($applyId));

            if (!textarea || typeof Jodit === 'undefined') return;

            const form = textarea.closest('form');
            const uploadUrl = @json($uploadRoute ? route($uploadRoute) : null);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            const options = {
                height: @json((int) $height),
                minHeight: 320,
                toolbarAdaptive: false,
                toolbarSticky: false,
                removeButtons: 'ai-commands,ai-assistant,speechRecognize,about,file,video,classSpan,spellcheck',
                showCharsCounter: false,
                showWordsCounter: false,
                showXPathInStatusbar: false,
                askBeforePasteHTML: false,
                defaultActionOnPaste: 'insert_as_html',
            };

            if (uploadUrl) {
                options.uploader = {
                    url: uploadUrl,
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    filesVariableName: () => 'image',
                    isSuccess: (response) => Boolean(response && response.url),
                    process: (response) => ({
                        files: response && response.url ? [response.url] : [],
                        isImages: response && response.url ? [true] : [],
                        path: '',
                        baseurl: '',
                        error: response && response.url ? 0 : 1,
                        msg: (response && response.message) || '',
                    }),
                };
            }

            const editor = Jodit.make(textarea, options);

            // The hidden textarea is the value that is submitted.
            const syncTextarea = () => {
                textarea.value = editor.value;
            };

            const syncCode = () => {
                if (!code || document.activeElement === code) return;
                code.value = editor.value;
            };

            editor.events.on('change', () => {
                syncTextarea();
                syncCode();
            });

            syncTextarea();
            syncCode();

            if (code) {
                let codeTimer = null;
                code.addEventListener('input', () => {
                    clearTimeout(codeTimer);
                    codeTimer = setTimeout(() => {
                        editor.value = code.value;
                        syncTextarea();
                    }, 300);
                });
            }

            const setCodeOpen = (open) => {
                if (!codePane || !toggle) return;
                codePane.hidden = !open;
                shell?.classList.toggle('is-split', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                const label = toggle.querySelector('span');
                if (label) label.textContent = open ? 'Hide HTML code' : 'Show HTML code';
                if (open) {
                    syncCode();
                    code.focus();
                    shell?.scrollIntoView({ block: 'nearest' });
                }
            };

            toggle?.addEventListener('click', () => setCodeOpen(codePane.hidden));

            apply?.addEventListener('click', () => {
                if (!code) return;
                editor.value = code.value;
                syncTextarea();
                editor.focus();
            });

            form?.addEventListener('submit', () => {
                // The HTML pane may hold unsaved edits; promote them first.
                if (codePane && !codePane.hidden && code && code.value !== editor.value) {
                    editor.value = code.value;
                }
                syncTextarea();
            });
        })();
    </script>
@endpush
