@extends('layouts.portal')

@section('title', 'Student Dashboard')
@section('banner-subtitle', 'শিক্ষার্থীর একাডেমিক ও ব্যক্তিগত তথ্যের সংক্ষিপ্তসার')

@section('content')
    @php
        $latest = $results->first();
        $latestResult = $latest['result'] ?? null;
        $latestSubjects = collect($latestResult['subjects'] ?? [])->take(4);
    @endphp

    <div class="portal-cards">
        <div class="portal-stat">
            <strong>{{ number_format($attendance['percentage'], 0) }}%</strong>
            <span>উপস্থিতি</span>
        </div>
        <div class="portal-stat">
            <strong>{{ $latestResult ? number_format($latestResult['gpa'], 2) : '—' }}</strong>
            <span>GPA / Average</span>
        </div>
        <div class="portal-stat">
            <strong>{{ str_pad((string) $notices->count(), 2, '0', STR_PAD_LEFT) }}</strong>
            <span>সাম্প্রতিক নোটিশ</span>
        </div>
        <div class="portal-stat">
            <strong>{{ str_pad((string) $assignments->count(), 2, '0', STR_PAD_LEFT) }}</strong>
            <span>অ্যাসাইনমেন্ট</span>
        </div>
    </div>

    <div class="portal-panels">
        <div class="portal-panel">
            <h2>সাম্প্রতিক ফলাফল</h2>
            <div class="portal-panel-body">
                <table class="portal-table">
                    <thead>
                        <tr><th>বিষয়</th><th class="num">নম্বর</th><th>গ্রেড</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($latestSubjects as $subject)
                            <tr>
                                <td>{{ $subject['subject']?->name ?? 'বিষয়' }}</td>
                                <td class="num">{{ $subject['obtained'] === null ? '—' : number_format((float) $subject['obtained'], 0) }}</td>
                                <td>{{ $subject['grade'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">এখনও কোনো ফলাফল প্রকাশিত হয়নি।</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($latest)
                    <p style="margin:12px 0 0"><a class="btn btn-purple btn-sm" href="{{ route('student.results') }}">সম্পূর্ণ ফলাফল</a></p>
                @endif
            </div>
        </div>

        <div class="portal-panel">
            <h2>আসন্ন পরীক্ষা</h2>
            <div class="portal-panel-body">
                <table class="portal-table">
                    <thead>
                        <tr><th>তারিখ</th><th>বিষয়</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($exams as $paper)
                            <tr>
                                <td>{{ $paper->exam_date?->format('d M') ?? '—' }}{{ $paper->start_time ? ' · '.substr($paper->start_time, 0, 5) : '' }}</td>
                                <td>{{ $paper->subject?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="empty">কোনো প্রকাশিত পরীক্ষার সময়সূচি নেই।</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p style="margin:12px 0 0"><a class="btn btn-outline btn-sm" href="{{ route('student.routine') }}">সম্পূর্ণ রুটিন</a></p>
            </div>
        </div>
    </div>

    <div class="portal-panel" style="margin-top:16px">
        <h2>Quick Access</h2>
        <div class="portal-panel-body">
            <div class="portal-quick">
                <a href="{{ route('student.attendance') }}">
                    <strong>Attendance</strong>
                    <span>উপস্থিতি</span>
                </a>
                <a href="{{ route('student.assignments') }}">
                    <strong>Assignments</strong>
                    <span>কাজ ও জমাদান</span>
                </a>
                <a href="{{ route('student.materials') }}">
                    <strong>Study Materials</strong>
                    <span>পাঠ্যসামগ্রী</span>
                </a>
            </div>
        </div>
    </div>

    <div class="portal-panels">
        <div class="portal-panel">
            <h2>ফি ও পেমেন্ট</h2>
            <div class="portal-panel-body">
                <table class="portal-table">
                    <tbody>
                        <tr><th>নির্ধারিত ফি</th><td class="num">{{ money($fees['total_charged']) }}</td></tr>
                        <tr><th>পরিশোধিত</th><td class="num">{{ money($fees['total_paid']) }}</td></tr>
                        <tr><th>বাকি</th><td class="num">{{ money($fees['balance']) }}</td></tr>
                        <tr><th>মেয়াদোত্তীর্ণ</th><td class="num">{{ money($fees['overdue']) }}</td></tr>
                    </tbody>
                </table>
                <p style="margin:12px 0 0"><a class="btn btn-outline btn-sm" href="{{ route('student.fees') }}">বিস্তারিত দেখুন</a></p>
            </div>
        </div>

        <div class="portal-panel">
            <h2>নোটিশ বোর্ড</h2>
            <div class="portal-panel-body">
                <table class="portal-table">
                    <tbody>
                        @forelse ($notices as $notice)
                            <tr>
                                <td><a href="{{ route('student.notices.show', $notice) }}">{{ $notice->title }}</a></td>
                                <td class="num">{{ ($notice->publish_at ?? $notice->created_at)?->format('d M') }}</td>
                            </tr>
                        @empty
                            <tr><td class="empty">কোনো নোটিশ প্রকাশিত হয়নি।</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p style="margin:12px 0 0"><a class="btn btn-outline btn-sm" href="{{ route('student.notices') }}">সব নোটিশ</a></p>
            </div>
        </div>
    </div>
@endsection
