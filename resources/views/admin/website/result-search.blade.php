@extends('layouts.app')

@section('title', 'Result Search')

@section('content')
    <x-page-header eyebrow="Website content" title="Result Search"
        subtitle="Control the public result search and see which examinations are currently publishable.">
        <x-slot:actions>
            <x-button :href="route('website.results')" variant="secondary" icon="external-link" target="_blank">View public results</x-button>
            <x-button :href="route('exams.index')" variant="secondary" icon="file-text">Examinations</x-button>
            <x-button :href="route('website-admin.index')" variant="secondary" icon="layout-dashboard">Website dashboard</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($exams->isEmpty())
        <div class="info-box" style="margin-bottom:16px;border-left:4px solid #d9ad55">
            <strong>এই মুহূর্তে কোনো রেজাল্ট পাবলিশ হয়নি।</strong>
            <p style="margin:6px 0 0">
                পাবলিক সার্চে ফলাফল দেখাতে হলে <a href="{{ route('exams.index') }}">Examinations</a> থেকে একটি পরীক্ষার ফলাফল
                <em>Publish</em> করতে হবে। ফলাফল প্রকাশিত হলে এই পেজে স্বয়ংক্রিয়ভাবে দেখা যাবে।
            </p>
        </div>
    @else
        <div class="info-box" style="margin-bottom:16px;border-left:4px solid #4fc66a">
            <strong>পাবলিক সার্চ লাইভ।</strong>
            {{ $exams->count() }}টি পরীক্ষা ও {{ number_format($publishedCount) }}টি শিক্ষার্থীর ফলাফল প্রকাশিত আছে।
            @if (! $settings['website_result_search_enabled'])
                <span style="color:#a83a3a">তবে নিচের টগল বন্ধ থাকায় পাবলিক সাইটে সার্চ দেখানো হচ্ছে না।</span>
            @endif
        </div>
    @endif

    <div class="grid-main-side">
        <div class="stack">
            <form method="POST" action="{{ route('website-admin.result-search.update') }}">
                @csrf
                @method('PUT')

                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Public result search</h3>
                            <p class="card-sub">The search form only appears when this is enabled and at least one result is published.</p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="span-2">
                                <input type="hidden" name="website_result_search_enabled" value="0">
                                <label class="checkbox">
                                    <input type="checkbox" name="website_result_search_enabled" value="1" @checked($settings['website_result_search_enabled'] === '1')>
                                    <span>Show the result search on the public website</span>
                                </label>
                            </div>

                            <x-input name="website_result_title" label="Page title" :value="$settings['website_result_title']" required maxlength="100" help="Shown in the page hero and browser title." />
                            <x-input name="website_result_subtitle" label="Subtitle" :value="$settings['website_result_subtitle']" maxlength="200" />
                            <div class="span-2">
                                <x-input name="website_result_help" label="Search help text" :value="$settings['website_result_help']" maxlength="250" help="Shown above the search form." />
                            </div>
                            <div class="span-2">
                                <x-input name="website_result_empty_message" label="Message when nothing is published" :value="$settings['website_result_empty_message']" required maxlength="200" help="Shown when no result is published or the search is switched off." />
                            </div>
                        </div>
                    </div>
                    <div class="card-footer" style="display:flex;justify-content:flex-end">
                        <x-button type="submit" variant="primary" icon="save">Save settings</x-button>
                    </div>
                </div>
            </form>

            <div class="card" style="margin-top:16px">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Published results</h3>
                        <p class="card-sub">Only examinations with published snapshots are searchable.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Examination</th>
                                <th>Session</th>
                                <th>Published</th>
                                <th>Results</th>
                                <th class="actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($exams as $exam)
                                <tr>
                                    <td>
                                        <div class="cell-title">{{ $exam->name }}</div>
                                        <div class="cell-sub">{{ ucfirst($exam->exam_type) }}</div>
                                    </td>
                                    <td>{{ $exam->academicSession?->name ?? '—' }}</td>
                                    <td class="cell-sub">{{ $exam->result_published_at?->format('d M Y, h:i A') ?? '—' }}</td>
                                    <td>{{ number_format($exam->result_snapshots_count) }}</td>
                                    <td class="actions">
                                        <a class="btn btn-secondary btn-sm" href="{{ route('exams.show', $exam) }}">Open exam</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="table-empty">No examination has published results yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">How it works</h3>
                    <p class="card-sub">Publishing stays in the academic module.</p>
                </div>
            </div>
            <div class="card-body">
                <ol style="margin:0;padding-left:18px;line-height:1.7">
                    <li>Create the examination and enter/approve marks.</li>
                    <li>Publish the result from the examination screen.</li>
                    <li>The public search becomes available automatically.</li>
                    <li>Turn the toggle off here to hide it without unpublishing.</li>
                </ol>
                <p class="field-hint" style="margin-top:12px">
                    Students search with their permanent Student ID (for example <span class="mono">2601001</span>).
                </p>
            </div>
        </div>
    </div>
@endsection
