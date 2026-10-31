@extends('layouts.app')

@section('title', $notice->exists ? 'Edit Notice' : 'New Notice')

@php($selected = old('classes', $selectedClasses ?? []))

@section('content')
    <x-page-header eyebrow="Communication" :title="$notice->exists ? 'Edit Notice' : 'New Notice'" subtitle="Draft first or publish immediately. Published notices appear on dashboards according to their audience and schedule.">
        <x-slot:actions>
            <x-button :href="route('notices.index')" variant="secondary" icon="arrow-left">Notices</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $notice->exists ? route('notices.update', $notice) : route('notices.store') }}" x-data="{ audience: '{{ old('audience', $notice->audience) }}' }">
        @csrf
        @if ($notice->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Notice Content</h3><p class="card-sub">Keep the title short and the body clear</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <div class="span-2"><x-input name="title" label="Title" :value="$notice->title" required placeholder="e.g. Half-yearly examination schedule" /></div>
                    <div class="span-2">
                        <x-rich-text name="body" label="Notice body" :value="$notice->body" upload-route="notices.image"
                            help="Use the toolbar for headings, lists, links and images. “Show HTML code” opens the markup side by side." />
                        @error('body_format')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Audience &amp; Schedule</h3><p class="card-sub">Control who sees the notice and when</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <x-select name="audience" label="Audience" :options="['all' => 'Everyone', 'students' => 'Students', 'staff' => 'Staff', 'classes' => 'Selected classes']" :selected="$notice->audience" required x-model="audience" />
                    <x-select name="status" label="Status" :options="['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']" :selected="$notice->status" required />
                    <x-input name="publish_at" label="Publish at" type="datetime-local" :value="$notice->publish_at?->format('Y-m-d\TH:i')" help="Blank publishes immediately when the status is Published." />
                    <x-input name="expires_at" label="Expires at" type="datetime-local" :value="$notice->expires_at?->format('Y-m-d\TH:i')" help="Optional. The notice stops showing after this time." />
                </div>

                <div x-show="audience === 'classes'" x-cloak style="margin-top:18px">
                    <div class="field-label" style="margin-bottom:8px">Selected classes <span class="required-mark">*</span></div>
                    @error('classes')<div class="field-error" style="margin-bottom:8px">{{ $message }}</div>@enderror
                    <div class="grid-3" style="gap:8px">
                        @forelse ($classes as $class)
                            <label class="checkbox">
                                <input type="checkbox" name="classes[]" value="{{ $class->id }}" @checked(in_array($class->id, array_map('intval', (array) $selected), true))>
                                <span>{{ $class->name }}</span>
                            </label>
                        @empty
                            <p class="muted" style="font-size:12px;margin:0">No classes are configured yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="info-box" style="margin-top:16px">
                    Published notices are filtered by their publish and expiry times. Selecting a specific audience does not send messages outside the system; use WhatsApp reminders from the fees module for direct messaging.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="route('notices.index')" variant="ghost">Cancel</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $notice->exists ? 'Save notice' : 'Create notice' }}</x-button>
            </div>
        </div>
    </form>
@endsection
