@once
    @push('head')
        <style>
            .routine-preview-dialog { width:min(1200px,96vw);height:min(900px,94vh);max-width:none;max-height:none;margin:auto;padding:0;border:0;border-radius:14px;background:#f3f5fa;box-shadow:0 24px 80px rgba(9,18,36,.38);overflow:hidden; }
            .routine-preview-dialog::backdrop { background:rgba(12,20,37,.62);backdrop-filter:blur(3px); }
            .routine-preview-head { min-height:48px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:6px 14px;background:#172b4d;color:#fff; }
            .routine-preview-head strong { font-size:13px;white-space:nowrap; }
            .routine-preview-actions { display:flex;align-items:center;gap:7px; }
            .routine-preview-action,.routine-preview-close { display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:5px 11px;border:1px solid rgba(255,255,255,.32);border-radius:6px;background:transparent;color:#fff;font-family:inherit;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;white-space:nowrap; }
            .routine-preview-action-primary { border-color:#8061e8;background:#7351db; }
            .routine-preview-close { width:32px;padding:0;font-size:19px; }
            .routine-preview-body { height:calc(100% - 48px);padding:10px 15px 15px; }
            .routine-preview-frame { display:block;width:100%;height:100%;border:0;border-radius:8px;background:#fff; }
            @media (max-width:640px) { .routine-preview-dialog { width:100vw;height:100dvh;border-radius:0; }.routine-preview-head { gap:6px;padding:6px 8px; }.routine-preview-head strong { font-size:11px; }.routine-preview-actions { gap:4px; }.routine-preview-action { padding:4px 7px;font-size:11px; }.routine-preview-body { padding:8px; } }
        </style>
    @endpush

    <dialog class="routine-preview-dialog" id="routine-preview-dialog" aria-labelledby="routine-preview-title">
        <div class="routine-preview-head">
            <strong id="routine-preview-title">{{ __('Class Routine Preview') }}</strong>
            <div class="routine-preview-actions">
                <a class="routine-preview-action" id="routine-preview-pdf" href="#">{{ __('Download PDF') }}</a>
                <button type="button" class="routine-preview-action routine-preview-action-primary" data-routine-preview-print>{{ __('Print') }}</button>
                <button type="button" class="routine-preview-close" data-routine-preview-close aria-label="{{ __('Close preview') }}">×</button>
            </div>
        </div>
        <div class="routine-preview-body"><iframe class="routine-preview-frame" id="routine-preview-frame" title="{{ __('Routine print preview') }}" src="about:blank"></iframe></div>
    </dialog>

    @push('scripts')
        <script>
            (() => {
                // Resolve the current dialog/frame at event time. Turbo replaces the
                // body on every visit, so capturing them in this closure would leave
                // the delegate pointing at detached nodes after navigating.
                const dialog = () => document.getElementById('routine-preview-dialog');
                const frame = () => document.getElementById('routine-preview-frame');
                const pdfLink = () => document.getElementById('routine-preview-pdf');

                const current = dialog();
                if (current && current.dataset.bound !== '1') {
                    current.dataset.bound = '1';

                    current.addEventListener('click', (event) => {
                        if (event.target === current) current.close();
                    });
                    current.addEventListener('close', () => {
                        const iframe = frame();
                        const link = pdfLink();
                        if (iframe) iframe.src = 'about:blank';
                        if (link) link.href = '#';
                    });
                }

                if (window.__routinePreviewDelegatesBound) return;
                window.__routinePreviewDelegatesBound = true;

                document.addEventListener('click', (event) => {
                    const trigger = event.target.closest('[data-routine-preview]');

                    if (trigger) {
                        const previewDialog = dialog();
                        const previewFrame = frame();
                        const previewPdf = pdfLink();
                        if (!previewDialog || !previewFrame || !previewPdf) return;

                        previewFrame.src = trigger.dataset.previewUrl;
                        previewPdf.href = trigger.dataset.previewPdfUrl;
                        previewDialog.showModal();
                    }

                    if (event.target.closest('[data-routine-preview-close]')) {
                        dialog()?.close();
                    }

                    if (event.target.closest('[data-routine-preview-print]')) {
                        frame()?.contentWindow?.print();
                    }
                });
            })();
        </script>
    @endpush
@endonce
