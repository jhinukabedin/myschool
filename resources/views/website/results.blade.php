@extends('layouts.public')

@section('title', $resultTitle)
@section('meta_description', $resultSubtitle)

@section('content')
    <section class="page-hero">
        <div class="container">
            <div class="breadcrumb">প্রচ্ছদ / {{ $resultTitle }}</div>
            <h1>{{ $resultTitle }}</h1>
            <p>{{ $resultSubtitle }}</p>
        </div>
    </section>

    <section class="section">
        <div class="container inner-grid">
            <div>
                @if (! $showFeed)
                    <div class="card article result-empty">
                        <div class="result-empty-icon">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </svg>
                        </div>
                        <h2 style="margin:0 0 8px">{{ $resultEmptyMessage }}</h2>
                        <p style="margin:0;color:#6b7684">ফলাফল প্রকাশিত হলে এখানে শিক্ষার্থী আইডি দিয়ে ফলাফল খোঁজা যাবে।</p>
                    </div>
                @else
                    <div class="card article result-search" style="padding:clamp(20px,4vw,30px)">
                        <h2 style="margin:0 0 4px;color:var(--navy);font-family:'Noto Serif Bengali'">ফলাফল খুঁজুন</h2>
                        <p style="margin:0 0 18px;color:#6b7684">{{ $resultHelp }}</p>

                        <form method="GET" action="{{ route('website.results') }}" class="form-grid">
                            <div class="field full">
                                <label for="exam_id">পরীক্ষা</label>
                                <select id="exam_id" name="exam_id" required>
                                    @foreach ($exams as $exam)
                                        <option value="{{ $exam->id }}" @selected($selectedExam?->id === $exam->id)>
                                            {{ $exam->name }}@if ($exam->academicSession) — {{ $exam->academicSession->name }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field full">
                                <label for="student_id">শিক্ষার্থী আইডি / রেজিস্ট্রেশন নম্বর</label>
                                <input id="student_id" name="student_id" value="{{ $lookup }}" placeholder="যেমন: 2601001" autocomplete="off" required>
                            </div>
                            <div class="field full" style="flex-direction:row;align-items:center;gap:10px;flex-wrap:wrap">
                                <button type="submit" class="btn btn-purple">ফলাফল দেখুন</button>
                                @if ($searched)
                                    <a href="{{ route('website.results') }}" class="btn btn-outline">নতুন করে খুঁজুন</a>
                                @endif
                            </div>
                        </form>
                    </div>

                    @if ($lookupError)
                        <div class="card article" style="margin-top:16px;border-left:4px solid #d9ad55">
                            <h3 style="margin:0 0 6px;color:var(--navy)">ফলাফল পাওয়া যায়নি</h3>
                            <p style="margin:0;color:#6b7684">{{ $lookupError }}</p>
                        </div>
                    @endif

                    @if ($result && $snapshot)
                        @php($data = $snapshot->result_data ?? [])
                        <div class="card article result-sheet" id="public-result" style="margin-top:16px;padding:0">
                            <div class="result-sheet-head">
                                <div>
                                    <div style="font-size:11px;letter-spacing:1.4px;color:#b8cbe0">{{ $siteNameEn }}</div>
                                    <h2>{{ $siteNameBn }}</h2>
                                    @if ($siteAddress)<p>{{ $siteAddress }}</p>@endif
                                    <p>{{ $selectedExam->name }}@if ($selectedExam->academicSession) · {{ $selectedExam->academicSession->name }}@endif</p>
                                </div>
                                <div style="text-align:right">
                                    <div style="font-size:11px;letter-spacing:1.4px;color:#b8cbe0">ACADEMIC RESULT</div>
                                    <strong style="display:block;font-size:18px;margin-top:2px">{{ $result['result'] ?? '—' }}</strong>
                                    <small style="color:#d6e2ed">প্রকাশিত: {{ $snapshot->published_at?->format('d M Y') ?? '—' }}</small>
                                </div>
                            </div>

                            <div class="result-sheet-body">
                                <div class="result-meta">
                                    <div><b>শিক্ষার্থীর নাম</b><span>{{ $student?->full_name ?? ($data['student_name'] ?? '—') }}</span></div>
                                    <div><b>শিক্ষার্থী আইডি</b><span>{{ $student?->student_code ?? ($data['student_code'] ?? '—') }}</span></div>
                                    <div><b>শ্রেণি</b><span>{{ $data['class_name'] ?? '—' }}</span></div>
                                    <div><b>শাখা</b><span>{{ $data['section_name'] ?? '—' }}</span></div>
                                    <div><b>রোল</b><span>{{ $data['roll_no'] ?? '—' }}</span></div>
                                    <div><b>শিক্ষাবর্ষ</b><span>{{ $selectedExam->academicSession?->name ?? '—' }}</span></div>
                                </div>

                                <table class="data-table" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>বিষয়</th>
                                            <th style="text-align:right">পূর্ণমান</th>
                                            <th style="text-align:right">প্রাপ্ত নম্বর</th>
                                            <th>গ্রেড</th>
                                            <th style="text-align:right">গ্রেড পয়েন্ট</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($result['subjects'] as $row)
                                            <tr>
                                                <td>{{ $row['subject']?->name ?? 'বিষয়' }}</td>
                                                <td style="text-align:right">{{ number_format((float) $row['full_marks'], 0) }}</td>
                                                <td style="text-align:right">
                                                    @if ($row['special_status'] === 'missing')
                                                        —
                                                    @elseif ($row['exempt'])
                                                        মওকুফ
                                                    @elseif ($row['absent'])
                                                        অনুপস্থিত
                                                    @else
                                                        {{ number_format((float) $row['obtained'], 2) }}
                                                    @endif
                                                </td>
                                                <td>{{ $row['grade'] }}</td>
                                                <td style="text-align:right">{{ $row['grade_point'] === null ? '—' : number_format((float) $row['grade_point'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <div class="result-summary">
                                    <div><b>{{ number_format((float) $result['total'], 2) }}</b><span>মোট নম্বর / {{ number_format((float) $result['full_total'], 0) }}</span></div>
                                    <div><b>{{ number_format((float) $result['percentage'], 2) }}%</b><span>শতকরা</span></div>
                                    <div><b>{{ number_format((float) $result['gpa'], 2) }}</b><span>GPA</span></div>
                                    <div><b>{{ $result['grade'] }}</b><span>গ্রেড</span></div>
                                </div>

                                <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                                    <span class="badge {{ $result['result'] === 'Pass' ? 'green' : ($result['result'] === 'Fail' ? 'red' : 'gold') }}">ফলাফল: {{ $result['result'] }}</span>
                                    @if (($result['failed_subjects'] ?? 0) > 0)
                                        <span class="badge red">ব্যর্থ বিষয়: {{ $result['failed_subjects'] }}</span>
                                    @endif
                                    @if (! empty($result['special_statuses']))
                                        <span class="badge gold">বিশেষ অবস্থা: {{ collect($result['special_statuses'])->map(fn ($s) => ucfirst($s))->implode(', ') }}</span>
                                    @endif
                                </div>

                                <div class="no-print" style="margin-top:18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap">
                                    <a href="{{ route('website.results') }}" class="btn btn-outline">নতুন করে খুঁজুন</a>
                                    <button type="button" class="btn btn-purple" onclick="window.print()">প্রিন্ট করুন</button>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
                @include('website._sidebar')

                <div class="card contact-card">
                    <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">সহায়তা প্রয়োজন?</h3>
                    <p style="margin:0 0 6px">ফলাফল সংক্রান্ত কোনো সমস্যা হলে বিদ্যালয় অফিসে যোগাযোগ করুন।</p>
                    @if ($sitePhone)<p style="margin:0">ফোন: {{ $sitePhone }}</p>@endif
                    @if ($siteEmail)<p style="margin:0">ইমেইল: {{ $siteEmail }}</p>@endif
                    <p style="margin:8px 0 0"><a href="{{ route('website.contact') }}">যোগাযোগ ফর্ম →</a></p>
                </div>
            </aside>
        </div>
    </section>
@endsection
