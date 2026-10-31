@extends('layouts.app')

@section('title', 'Contact Message')

@section('content')
    <x-page-header eyebrow="Website" title="Contact Message" :subtitle="$message->subject ?: 'Message from '.$message->name">
        <x-slot:actions>
            <x-button :href="route('messages.index')" variant="secondary" icon="arrow-left">Inbox</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="grid-main-side">
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">{{ $message->subject ?: 'No subject' }}</h3><p class="card-sub">{{ $message->created_at?->format('d M Y, h:i A') }}</p></div>
                <x-badge :color="$message->status_color">{{ ucfirst($message->status) }}</x-badge>
            </div>
            <div class="card-body">
                <p style="white-space:pre-line">{{ $message->message }}</p>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Sender</h3></div></div>
                <div class="card-body">
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value">{{ $message->name }}</span></div>
                        <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">{{ $message->email ?: '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value">{{ $message->phone ?: '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">IP</span><span class="detail-value">{{ $message->ip_address ?: '—' }}</span></div>
                    </div>
                </div>
            </div>

            @can('website.manage')
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Status</h3></div></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('messages.status', $message) }}">
                            @csrf
                            <div class="stack">
                                <x-select name="status" label="Message status" :options="['new' => 'New', 'read' => 'Read', 'archived' => 'Archived']" :selected="$message->status" required />
                                <x-button type="submit" variant="primary" icon="check">Update status</x-button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
