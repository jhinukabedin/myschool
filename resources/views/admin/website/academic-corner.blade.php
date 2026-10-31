@extends('layouts.app')

@section('title', 'Academic Corner')

@section('content')
    <x-page-header eyebrow="Website content" title="Academic Corner"
        subtitle="Cards shown in the homepage academic corner. Add an icon, title, sub title and link. Only enabled cards appear on the public site.">
        <x-slot:actions>
            <x-button :href="route('website-admin.index')" variant="secondary" icon="layout-dashboard">Website dashboard</x-button>
            <x-button :href="route('website.home')" variant="secondary" icon="external-link" target="_blank">View website</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="info-box" style="border-color:#f3c2c2;margin-bottom:16px">
            <strong>Please fix the following:</strong>
            <ul style="margin:6px 0 0 18px">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Academic cards</h3>
                <p class="card-sub">Add a card with an icon, title, sub title and link. Up to {{ $limit }} cards are allowed and each fills one tile of the homepage grid.</p>
            </div>
            <x-badge color="violet">{{ $cards->count() }} / {{ $limit }}</x-badge>
        </div>
        <div class="card-body">
            @if ($cards->isNotEmpty())
                <p class="field-hint" style="margin:0 0 10px">
                    Cards appear on the homepage in this order. Use the arrows to move a card up or down, or set a
                    <strong>sort order</strong> number when editing (lower numbers come first).
                </p>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:110px">Order</th>
                                <th style="width:64px">Icon</th>
                                <th>Title</th>
                                <th>Sub title</th>
                                <th>Link</th>
                                <th style="width:110px">Status</th>
                                <th class="actions" style="width:150px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cards as $card)
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:4px">
                                            <span class="mono" style="min-width:18px">{{ $loop->iteration }}</span>
                                            <form method="POST" action="{{ route('website-admin.academic-corner.move', $card) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="up">
                                                <button type="submit" class="btn-icon" title="Move up" @disabled($loop->first)>
                                                    <x-icon name="arrow-up" :size="14" />
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('website-admin.academic-corner.move', $card) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="down">
                                                <button type="submit" class="btn-icon" title="Move down" @disabled($loop->last)>
                                                    <x-icon name="arrow-down" :size="14" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td>
                                        @if (\App\Support\WebsiteIcons::exists($card->category))
                                            <span class="table-avatar"><x-icon :name="$card->category" :size="16" /></span>
                                        @else
                                            <span class="muted">{{ $card->category ?: '▣' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="cell-clamp" title="{{ $card->title }}">{{ $card->title }}</div>
                                        <details style="margin-top:4px">
                                            <summary class="cell-sub" style="cursor:pointer">Edit card</summary>
                                            <form method="POST" action="{{ route('website-admin.academic-corner.update', $card) }}" style="display:grid;gap:6px;margin-top:8px;min-width:280px">
                                                @csrf
                                                @method('PUT')
                                                <label class="field">
                                                    <span class="field-label">Icon</span>
                                                    <select class="select" name="icon" aria-label="Icon">
                                                        @foreach ($icons as $value => $label)
                                                            <option value="{{ $value }}" @selected(($card->category ?? '') === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label class="field">
                                                    <span class="field-label">Title</span>
                                                    <input class="input" name="title" value="{{ $card->title }}" required maxlength="120" aria-label="Title">
                                                </label>
                                                <label class="field">
                                                    <span class="field-label">Sub title</span>
                                                    <input class="input" name="subtitle" value="{{ $card->subtitle }}" maxlength="160" aria-label="Sub title">
                                                </label>
                                                <label class="field">
                                                    <span class="field-label">Link</span>
                                                    <input class="input" name="link_url" value="{{ $card->link_url }}" placeholder="/academics or https://..." required maxlength="500" aria-label="Link">
                                                </label>
                                                <div style="display:flex;gap:6px">
                                                    <label class="field" style="width:110px">
                                                        <span class="field-label">Sort order</span>
                                                        <input class="input" type="number" min="0" max="9999" name="sort_order" value="{{ $card->sort_order }}" aria-label="Sort order">
                                                    </label>
                                                </div>
                                                <input type="hidden" name="is_active" value="0">
                                                <label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked($card->status === 'published')> <span>Show on the homepage</span></label>
                                                <button class="btn btn-secondary btn-sm" type="submit">Save card</button>
                                            </form>
                                        </details>
                                    </td>
                                    <td><div class="cell-clamp" title="{{ $card->subtitle }}">{{ $card->subtitle ?: '—' }}</div></td>
                                    <td><code class="cell-clamp" title="{{ $card->link_url }}">{{ $card->link_url ?: '—' }}</code></td>
                                    <td>
                                        <x-badge :color="$card->status === 'published' ? 'success' : 'muted'">{{ $card->status === 'published' ? 'Enabled' : 'Disabled' }}</x-badge>
                                    </td>
                                    <td class="actions">
                                        <form method="POST" action="{{ route('website-admin.academic-corner.update', $card) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="icon" value="{{ $card->category }}">
                                            <input type="hidden" name="title" value="{{ $card->title }}">
                                            <input type="hidden" name="subtitle" value="{{ $card->subtitle }}">
                                            <input type="hidden" name="link_url" value="{{ $card->link_url }}">
                                            <input type="hidden" name="sort_order" value="{{ $card->sort_order }}">
                                            <input type="hidden" name="is_active" value="{{ $card->status === 'published' ? '0' : '1' }}">
                                            <button type="submit" class="btn-icon" title="{{ $card->status === 'published' ? 'Disable' : 'Enable' }}">
                                                <x-icon :name="$card->status === 'published' ? 'lock' : 'check-circle'" :size="15" />
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('website-admin.academic-corner.destroy', $card) }}" onsubmit="return confirm('Delete this academic card?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-icon" title="Delete"><x-icon name="trash" :size="15" /></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="info-box" style="margin-bottom:12px">No academic cards yet. Add up to {{ $limit }} cards below.</div>
            @endif

            <hr style="border:0;border-top:1px solid var(--border);margin:14px 0">

            @if ($cards->count() < $limit)
                <p class="card-sub" style="margin-top:0"><strong>{{ $cards->count() }} / {{ $limit }}</strong> cards used. The next card is added at position {{ $cards->count() + 1 }} unless you set a different sort order.</p>
                <form method="POST" action="{{ route('website-admin.academic-corner.store') }}">
                    @csrf
                    <div class="form-grid">
                        <x-select name="icon" label="Icon" :options="$icons" :placeholder="false" help="Small icon shown inside the card." />
                        <x-input name="title" label="Card title" placeholder="e.g. ক্লাস ও বিষয়" required />
                        <x-input name="subtitle" label="Sub title" placeholder="e.g. বিষয়ভিত্তিক তথ্য" />
                        <x-input name="link_url" label="Link" placeholder="/academics or https://..." required />
                        <x-input name="sort_order" label="Sort order" type="number" min="0" max="9999" :value="$cards->count() + 1" help="Lower numbers appear first." />
                    </div>
                    <input type="hidden" name="is_active" value="1">
                    <x-button type="submit" variant="outline" icon="plus">Add academic card</x-button>
                </form>
            @else
                <div class="info-box">You have reached the maximum of {{ $limit }} academic cards. Delete one above to add another.</div>
            @endif
        </div>
    </div>
@endsection
