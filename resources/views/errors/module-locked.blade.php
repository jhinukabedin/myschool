@extends('layouts.app')

@section('title', 'Module locked')

@section('content')
    <div class="card" style="max-width:720px;margin:32px auto;text-align:center;padding:42px 28px">
        <div style="width:70px;height:70px;border-radius:50%;background:rgba(217,173,85,.14);display:grid;place-items:center;margin:0 auto 18px;color:#d9ad55">
            <x-icon name="lock" :size="30" />
        </div>
        <h2 style="margin:0 0 8px">{{ $moduleName }} is not available</h2>
        <p style="margin:0 0 8px;color:var(--muted)">
            This page is not enabled for your school yet. The super administrator can turn it on from Subscription &amp; Modules.
        </p>
        <p style="margin:0 0 22px;color:var(--muted);font-size:13px">
            Website content management remains available on every plan.
        </p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <x-button :href="route('dashboard')" variant="primary" icon="layout-dashboard">Back to dashboard</x-button>
            @can('subscription.view')
                <x-button :href="route('subscription.index')" variant="secondary" icon="key">Subscription &amp; modules</x-button>
            @endcan
        </div>
    </div>
@endsection
