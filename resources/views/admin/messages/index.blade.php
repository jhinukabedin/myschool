@extends('layouts.app')

@section('title', 'Contact Messages')

@section('content')
    <x-page-header eyebrow="Website" title="Contact Messages" :subtitle="$newCount.' unread message(s) from the public contact form.'">
        <x-slot:actions>
            <x-button :href="route('website-content.index')" variant="secondary" icon="layout-dashboard">Website content</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Inbox</h3><p class="card-sub">{{ $messages->total() }} message(s)</p></div>
            <form method="GET" action="{{ route('messages.index') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field">
                    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email or message">
                </div>
                <div class="field">
                    <select class="select" name="status">
                        <option value="">Any status</option>
                        @foreach (['new' => 'New', 'read' => 'Read', 'archived' => 'Archived'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>From</th><th>Subject</th><th>Status</th><th>Received</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($messages as $message)
                        <tr>
                            <td>
                                <div class="cell-title">{{ $message->name }}</div>
                                <div class="cell-sub">{{ $message->email ?: $message->phone }}</div>
                            </td>
                            <td>{{ $message->subject ?: Str::limit($message->message, 60) }}</td>
                            <td><x-badge :color="$message->status_color">{{ ucfirst($message->status) }}</x-badge></td>
                            <td class="cell-sub">{{ $message->created_at?->format('d M Y, h:i A') }}</td>
                            <td class="actions"><a class="btn btn-secondary btn-sm" href="{{ route('messages.show', $message) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">No contact messages yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $messages->links() }}
    </div>
@endsection
