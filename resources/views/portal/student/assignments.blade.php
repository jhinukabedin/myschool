@extends('layouts.portal')

@section('title', 'Assignments')
@section('banner-subtitle', 'অ্যাসাইনমেন্ট, ডেডলাইন ও জমাদান')

@section('content')
    <div class="portal-cards">
        <div class="portal-stat">
            <strong>{{ str_pad((string) $assignments->count(), 2, '0', STR_PAD_LEFT) }}</strong>
            <span>মোট অ্যাসাইনমেন্ট</span>
        </div>
        <div class="portal-stat">
            <strong>{{ str_pad((string) $pending, 2, '0', STR_PAD_LEFT) }}</strong>
            <span>চলমান</span>
        </div>
        <div class="portal-stat">
            <strong>{{ str_pad((string) $overdue, 2, '0', STR_PAD_LEFT) }}</strong>
            <span>মেয়াদোত্তীর্ণ</span>
        </div>
        <div class="portal-stat">
            <strong>{{ $enrollment?->class?->name ?? '—' }}</strong>
            <span>শ্রেণি</span>
        </div>
    </div>

    <div class="portal-panel" style="margin-top:16px">
        <h2>অ্যাসাইনমেন্ট</h2>
        <div class="portal-panel-body">
            <div class="table-wrap">
                <table class="portal-table">
                    <thead>
                        <tr><th>বিষয়</th><th>অ্যাসাইনমেন্ট</th><th>Deadline</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($assignments as $assignment)
                            @php
                                $overdue = $assignment->due_date && $assignment->due_date->lt(today());
                                $dueSoon = $assignment->due_date && ! $overdue && $assignment->due_date->lte(now()->addDays(3));
                            @endphp
                            <tr>
                                <td>{{ $assignment->subtitle ?: ($assignment->category ?: '—') }}</td>
                                <td>
                                    <strong>{{ $assignment->title }}</strong>
                                    @if ($assignment->excerpt)
                                        <div class="small muted">{{ $assignment->excerpt }}</div>
                                    @endif
                                </td>
                                <td class="mono">{{ $assignment->due_date?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    @if ($overdue)
                                        <span class="status status-overdue">Overdue</span>
                                    @elseif ($dueSoon)
                                        <span class="status status-pending">Due soon</span>
                                    @else
                                        <span class="status">Open</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($assignment->file_url)
                                        <a class="btn btn-outline btn-sm" href="{{ $assignment->file_url }}" target="_blank" rel="noopener">ফাইল</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">
                                <strong>এই শ্রেণির জন্য কোনো অ্যাসাইনমেন্ট প্রকাশিত হয়নি।</strong><br>
                                শিক্ষক অ্যাসাইনমেন্ট প্রকাশ করলে এখানে দেখা যাবে।
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="portal-panel" style="margin-top:16px">
        <h2>জমাদানের নিয়ম</h2>
        <div class="portal-panel-body">
            <div class="portal-note">
                অ্যাসাইনমেন্টের ফাইল ডাউনলোড করে নির্ধারিত সময়ের মধ্যে শ্রেণিশিক্ষকের কাছে জমা দিন। ডেডলাইনের পরে জমা দিলে শিক্ষকের অনুমোদন প্রয়োজন হতে পারে।
            </div>
        </div>
    </div>
@endsection
