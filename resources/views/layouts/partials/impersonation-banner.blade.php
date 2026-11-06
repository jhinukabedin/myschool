@php
    $impersonator = session('impersonator_id')
        ? \App\Models\User::query()->find(session('impersonator_id'))
        : null;
@endphp

@if ($impersonator && auth()->check() && auth()->id() !== $impersonator->id)
    <style>
        .impersonation-bar{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:9999;display:flex;align-items:center;gap:12px;max-width:calc(100vw - 32px);padding:9px 12px 9px 16px;border-radius:999px;background:#3b1d6e;color:#fff;box-shadow:0 12px 32px rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.18);font-size:12.5px;line-height:1.4}
        .impersonation-bar strong{color:#fff}
        .impersonation-bar form{margin:0;flex:none}
        .impersonation-return{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:999px;padding:7px 12px;background:#fff;color:#3b1d6e;font:inherit;font-weight:700;cursor:pointer;white-space:nowrap}
        .impersonation-return:hover{background:#f1eaff}
        @media (max-width:640px){.impersonation-bar{border-radius:14px;flex-wrap:wrap;justify-content:center;text-align:center;font-size:12px;bottom:10px;padding:10px}}
    </style>

    <div class="impersonation-bar" role="status">
        <span>
            Viewing as <strong>{{ auth()->user()->name }}</strong>
            @if (auth()->user()->role)
                ({{ auth()->user()->role->name }})
            @endif
        </span>
        <form method="POST" action="{{ route('impersonate.stop') }}">
            @csrf
            <button type="submit" class="impersonation-return">
                <x-icon name="undo" :size="14" /> Return to {{ $impersonator->name }}
            </button>
        </form>
    </div>
@endif
