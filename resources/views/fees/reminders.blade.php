@extends('layouts.app')

@section('title', 'WhatsApp Fee Reminders')

@section('content')
    <x-page-header eyebrow="Fees" title="WhatsApp Reminders" :subtitle="$session ? 'Send outstanding fee reminders for '.$session->name.' through a configured provider.' : 'No academic session is configured.'">
        <x-slot:actions>
            <x-button :href="route('fees.collection.index')" variant="secondary" icon="wallet">Collection ledger</x-button>
        </x-slot:actions>
    </x-page-header>

    @unless ($configured)
        <div class="alert alert-warning">
            <span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span>
            <div>
                <div class="alert-title">WhatsApp is not configured</div>
                <span>No message can be sent until a provider, credentials and template are saved below. The system never reports a simulated delivery.</span>
            </div>
        </div>
    @else
        <div class="alert alert-success">
            <span class="alert-icon"><x-icon name="check-circle" :size="17" /></span>
            <div><strong>{{ $providerLabel }}</strong> is configured. Delivery is only confirmed when the provider returns a message ID.</div>
        </div>
    @endunless

    <div class="grid-main-side">
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Students with dues</h3><p class="card-sub">{{ $students->total() }} student(s) with an outstanding balance</p></div>
                <form method="GET" class="filter-bar" style="margin:0">
                    <div class="field grow" style="min-width:190px">
                        <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search student or guardian">
                    </div>
                    <x-button type="submit" variant="secondary" icon="search">Search</x-button>
                </form>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Guardian phone</th><th class="num">Outstanding</th><th class="actions">Reminder</th></tr></thead>
                    <tbody>
                        @forelse ($students as $student)
                            @php($due = (float) ($dues[$student->id] ?? 0))
                            <tr>
                                <td>
                                    <div class="cell-main">
                                        <span class="table-avatar">{{ Str::substr($student->full_name, 0, 1) }}</span>
                                        <div>
                                            <div class="cell-title">{{ $student->full_name }}</div>
                                            <div class="cell-sub">{{ $student->admission_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $student->primaryGuardian()?->phone ?? 'No phone' }}</div>
                                    <div class="cell-sub">{{ $student->primaryGuardian()?->name }}</div>
                                </td>
                                <td class="num text-danger" style="font-weight:600">{{ money($due) }}</td>
                                <td class="actions">
                                    @can('communication.whatsapp')
                                        <form method="POST" action="{{ route('fees.reminders.send', $student) }}" onsubmit="return confirm('Send a WhatsApp reminder to this guardian?')">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm" @disabled(! $configured || $due <= 0 || ! $student->primaryGuardian()?->phone)>
                                                <x-icon name="message-circle" :size="13" /> Send
                                            </button>
                                        </form>
                                    @else
                                        <span class="muted" style="font-size:11px">Not permitted</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="table-empty">No students with outstanding dues match your search.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $students->links() }}
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header">
                    <div><h3 class="card-title">Provider configuration</h3><p class="card-sub">Credentials are stored server-side</p></div>
                    <x-badge :color="$configured ? 'success' : 'warning'">{{ $configured ? 'Ready' : 'Set up required' }}</x-badge>
                </div>
                @can('settings.manage')
                    <form method="POST" action="{{ route('fees.reminders.settings') }}">
                        @csrf
                        <div class="form-section">
                            <div class="form-grid">
                                <x-select name="whatsapp_provider" label="Provider" :options="['none' => 'Disabled', 'meta' => 'Meta WhatsApp Cloud API', 'generic' => 'Generic HTTP gateway']" :selected="$settings['whatsapp_provider']" required />
                                <x-input name="whatsapp_language" label="Language code" :value="$settings['whatsapp_language']" placeholder="en" />
                                <div class="span-2"><x-input name="whatsapp_api_url" label="API base URL" type="url" :value="$settings['whatsapp_api_url']" help="Meta: https://graph.facebook.com/v20.0. Generic: full endpoint received from your gateway." /></div>
                                <x-input name="whatsapp_phone_number_id" label="Phone number ID" :value="$settings['whatsapp_phone_number_id']" help="Required for Meta Cloud API." />
                                <x-input name="whatsapp_template_name" label="Template name" :value="$settings['whatsapp_template_name']" help="Approved Meta template, e.g. fee_reminder." />
                                <div class="span-2">
                                    <x-input name="whatsapp_token" label="Access token" type="password" :placeholder="$settings['has_token'] ? 'Token already saved — leave blank to keep it' : 'Paste provider token'" autocomplete="new-password" help="Blank keeps the existing token. Tokens are never displayed after saving." />
                                </div>
                            </div>
                        </div>
                        <div class="form-section" style="display:flex;justify-content:flex-end">
                            <x-button type="submit" variant="primary" icon="save">Save WhatsApp settings</x-button>
                        </div>
                    </form>
                @else
                    <div class="card-body">
                        <div class="info-box">Only an administrator with settings permission can change provider credentials.</div>
                    </div>
                @endcan
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Delivery policy</h3></div></div>
                <div class="card-body">
                    <div class="info-box">
                        A reminder is rejected when WhatsApp is disabled, credentials are missing, the guardian has no phone number, or the provider returns an error. Every accepted message is written to the audit log with its provider message ID.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
