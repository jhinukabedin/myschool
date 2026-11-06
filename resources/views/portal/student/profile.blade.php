@extends('layouts.portal')

@section('title', 'My Profile')
@section('banner-subtitle', 'আপনার ব্যক্তিগত ও শিক্ষার্থী তথ্য')

@section('content')
    <div class="portal-panel">
        <h2>শিক্ষার্থী প্রোফাইল</h2>
        <div class="portal-panel-body">
            <div class="profile-grid">
                <div>
                    <div class="profile-avatar">
                        @if ($student->photo_url)
                            <img src="{{ $student->photo_url }}" alt="{{ $student->full_name }}">
                        @else
                            {{ Str::upper(Str::substr($student->first_name, 0, 1).Str::substr($student->last_name, 0, 1)) }}
                        @endif
                    </div>
                    <p class="small muted" style="margin:10px 0 0">ছবি পরিবর্তনের জন্য স্কুল অফিসে যোগাযোগ করুন।</p>
                </div>
                <div>
                    <div class="info-list">
                        <div><b>নাম</b><span>{{ $student->full_name }}</span></div>
                        <div><b>Student ID</b><span class="mono">{{ $student->student_code ?? '—' }}</span></div>
                        <div><b>শ্রেণি</b><span>{{ $enrollment?->class?->name ?? '—' }}</span></div>
                        <div><b>শাখা</b><span>{{ $enrollment?->section?->name ?? '—' }}</span></div>
                        <div><b>রোল</b><span class="mono">{{ $enrollment?->roll_no ?? '—' }}</span></div>
                        <div><b>জন্ম তারিখ</b><span>{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</span></div>
                        <div><b>লিঙ্গ</b><span>{{ ucfirst($student->gender ?? '—') }}</span></div>
                        <div><b>রক্তের গ্রুপ</b><span>{{ $student->blood_group ?? '—' }}</span></div>
                        <div><b>ধর্ম</b><span>{{ $student->religion ?? '—' }}</span></div>
                        <div><b>মোবাইল</b><span>{{ $student->phone ?? '—' }}</span></div>
                        <div><b>ইমেইল</b><span>{{ $student->email ?? '—' }}</span></div>
                        <div><b>শিক্ষাবর্ষ</b><span>{{ $session?->name ?? '—' }}</span></div>
                        <div><b>অবস্থা</b><span><span class="badge badge-success">{{ $student->status_label }}</span></span></div>
                        <div style="grid-column:1/-1"><b>ঠিকানা</b><span>{{ collect([$student->address, $student->city, $student->state, $student->postal_code])->filter()->implode(', ') ?: '—' }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="portal-panels">
        <div class="portal-panel">
            <h2>অভিভাবক</h2>
            <div class="portal-panel-body">
                <div class="list">
                    @forelse ($student->guardians as $guardian)
                        <div class="list-item" style="padding-left:0;padding-right:0">
                            <div>
                                <strong>{{ $guardian->name }}</strong>
                                <small>{{ $guardian->relation ?? 'Guardian' }}{{ $guardian->phone ? ' · '.$guardian->phone : '' }}{{ $guardian->email ? ' · '.$guardian->email : '' }}</small>
                                @if ($guardian->address)<small>{{ $guardian->address }}</small>@endif
                            </div>
                            @if ($guardian->is_primary)<span class="badge badge-info">Primary</span>@endif
                        </div>
                    @empty
                        <div class="empty">কোনো অভিভাবকের তথ্য সংরক্ষিত নেই।</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="portal-panel">
            <h2>অ্যাকাউন্ট</h2>
            <div class="portal-panel-body">
                <p class="portal-note">পাসওয়ার্ড নিয়মিত পরিবর্তন করুন এবং কারও সাথে শেয়ার করবেন না।</p>
                <p style="margin:14px 0 0"><a class="btn btn-outline" href="{{ route('password.change') }}">পাসওয়ার্ড পরিবর্তন করুন</a></p>
            </div>
        </div>
    </div>

    <div class="portal-panel" style="margin-top:16px">
        <h2>শিক্ষার্থী তথ্যের ইতিহাস</h2>
        <div class="portal-panel-body">
            <div class="table-wrap">
                <table class="portal-table">
                    <thead><tr><th>শিক্ষাবর্ষ</th><th>শ্রেণি</th><th>শাখা</th><th>রোল</th><th>অবস্থা</th></tr></thead>
                    <tbody>
                        @forelse ($student->enrollments->sortByDesc('academic_session_id') as $row)
                            <tr>
                                <td>{{ $row->academicSession?->name }}</td>
                                <td>{{ $row->class?->name }}</td>
                                <td>{{ $row->section?->name ?? '—' }}</td>
                                <td class="mono">{{ $row->roll_no ?? '—' }}</td>
                                <td><span class="badge {{ $row->status === 'active' ? 'badge-success' : 'badge-warning' }}">{{ ucfirst($row->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">কোনো ভর্তি তথ্য পাওয়া যায়নি।</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
