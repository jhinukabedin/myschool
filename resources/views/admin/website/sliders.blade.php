@extends('layouts.app')

@section('title', 'Slider Area')

@section('content')
    <x-page-header eyebrow="Website content" title="Slider Area"
        subtitle="Homepage hero slides. Only enabled slides appear on the public website.">
        <x-slot:actions>
            <x-button :href="route('website-content.create', ['type' => 'slider'])" variant="primary" icon="plus">Add New Slider</x-button>
            <x-button :href="route('website-admin.hero')" variant="secondary" icon="arrow-left">Hero Section</x-button>
            <x-button :href="route('website.home')" variant="secondary" icon="external-link" target="_blank">View website</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Slides</h3>
                <p class="card-sub">{{ $sliders->count() }} slider record(s)</p>
            </div>
        </div>

        @if ($sliders->isEmpty())
            <div class="card-body">
                <div class="info-box">No sliders yet. Click <strong>Add New Slider</strong> to create the first one.</div>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:104px">Preview</th>
                            <th>Slide</th>
                            <th class="hide-sm" style="width:150px">Tagline</th>
                            <th class="hide-lg">Button &amp; link</th>
                            <th style="width:110px">Status</th>
                            <th class="actions" style="width:150px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sliders as $slider)
                            <tr>
                                <td>
                                    @if ($slider->image_url)
                                        <img src="{{ $slider->image_url }}" alt="" class="table-thumb" loading="lazy">
                                    @else
                                        <span class="table-thumb-empty">No image</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="cell-main">
                                        <div style="min-width:0">
                                            <div class="cell-title cell-clamp" title="{{ $slider->title }}">{{ $slider->title ?: 'Untitled slide' }}</div>
                                            <div class="cell-sub cell-clamp" title="{{ $slider->excerpt }}">{{ $slider->excerpt ?: '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hide-sm">
                                    @if ($slider->category)
                                        <x-badge color="violet">{{ $slider->category }}</x-badge>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td class="hide-lg">
                                    @if ($slider->button_label || $slider->link_url)
                                        <div class="cell-clamp" title="{{ $slider->button_label }}">{{ $slider->button_label ?: 'View' }}</div>
                                        @if ($slider->link_url)
                                            <div class="cell-sub cell-clamp" title="{{ $slider->link_url }}"><code>{{ $slider->link_url }}</code></div>
                                        @endif
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <x-badge :color="$slider->status === 'published' ? 'success' : 'muted'">{{ ucfirst($slider->status) }}</x-badge>
                                </td>
                                <td class="actions">
                                    <a href="{{ route('website-content.edit', $slider) }}" class="btn-icon" title="Edit slide"><x-icon name="edit" :size="15" /></a>
                                    <form method="POST" action="{{ route('website-content.toggle', $slider) }}">
                                        @csrf
                                        <button type="submit" class="btn-icon" title="{{ $slider->status === 'published' ? 'Disable' : 'Enable' }}">
                                            <x-icon :name="$slider->status === 'published' ? 'lock' : 'check-circle'" :size="15" />
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('website-content.destroy', $slider) }}" onsubmit="return confirm('Delete this slider?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icon" title="Delete slide"><x-icon name="trash" :size="15" /></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">No sliders yet. Click <strong>Add New Slider</strong> to create the first one.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection