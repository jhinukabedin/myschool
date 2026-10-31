@extends('layouts.app')

@section('title', $student->full_name)

@section('content')
    <x-page-header eyebrow="Student Profile" :title="$student->full_name" :subtitle="'Student ID '.($student->student_code ?? '—').' · Admission '.$student->admission_no.' · '.($enrollment ? $enrollment->class?->name.' · Section '.$enrollment->section?->name.' · Roll '.$enrollment->roll_no : 'No active enrolment')">
        <x-slot:actions>
            @can('fees.collect')
                <x-button :href="route('fees.collection.show', $student)" variant="secondary" icon="wallet">Collect fees</x-button>
            @endcan
            @can('students.update')
                <x-button :href="route('students.edit', $student)" variant="primary" icon="edit">Edit profile</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid-main-side">
        <div class="stack">
            <div class="card">
                <div class="card-body">
                    <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
                        @if ($student->photo_url)
                            <img src="{{ $student->photo_url }}" alt="{{ $student->full_name }}" style="width:88px;height:88px;border-radius:20px;object-fit:cover;border:1px solid var(--line-strong)">
                        @else
                            <div class="brand-mark" style="width:88px;height:88px;border-radius:20px;font-size:30px;font-weight:700">{{ Str::substr($student->full_name, 0, 1) }}</div>
                        @endif
                        <div style="flex:1;min-width:200px">
                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
                                <x-badge :color="$student->status_color">{{ $student->status_label }}</x-badge>
                                <x-badge color="info">ID {{ $student->student_code ?? '—' }}</x-badge>
                                <x-badge color="violet">{{ $student->admission_no }}</x-badge>
                                @if ($student->blood_group)<x-badge color="danger">{{ $student->blood_group }}</x-badge>@endif
                            </div>
                            <div class="page-subtitle">{{ $student->address ?? 'No address recorded' }}{{ $student->city ? ', '.$student->city : '' }}</div>
                            <div class="muted" style="font-size:12px;margin-top:6px">
                                {{ ucfirst($student->gender) }} · DOB {{ $student->date_of_birth?->format('d M Y') }} · Admitted {{ $student->admission_date?->format('d M Y') }}
                            </div>
                        </div>
                        <div class="kpi"><div class="kpi-label">Outstanding</div><div class="kpi-value {{ $totalDue > 0 ? 'text-danger' : 'text-success' }}">{{ money($totalDue) }}</div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Fee Ledger</h3><p class="card-sub">{{ $session?->name }} session</p></div>
                    @if ($assignments->isNotEmpty())
                        <span class="badge badge-muted">{{ $assignments->count() }} fee rows</span>
                    @endif
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Fee</th><th>Period</th><th>Due date</th><th class="num">Net</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr>
                                    <td>{{ $assignment->category?->name }}</td>
                                    <td class="muted">{{ $assignment->period ?? 'One-time' }}</td>
                                    <td>{{ $assignment->due_date?->format('d M Y') }}</td>
                                    <td class="num">{{ money($assignment->net_amount) }}</td>
                                    <td class="num text-success">{{ money($assignment->paid_amount) }}</td>
                                    <td class="num {{ $assignment->balance > 0 ? 'text-danger' : '' }}">{{ money($assignment->balance) }}</td>
                                    <td>
                                        <x-badge :color="match($assignment->status) { 'paid' => 'success', 'partial' => 'warning', 'waived' => 'info', default => 'muted' }">{{ ucfirst($assignment->status) }}</x-badge>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="table-empty">No fee rows for the current session.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div><h3 class="card-title">Payment History</h3><p class="card-sub">Completed and reversed receipts remain in the ledger</p></div>
                    @can('fees.collect')
                        <a href="{{ route('fees.collection.show', $student) }}" class="btn btn-ghost btn-sm">Open collection <x-icon name="arrow-right" :size="13" /></a>
                    @endcan
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td class="mono" style="font-size:11px">{{ $payment->receipt_no }}</td>
                                    <td>{{ $payment->paid_at?->format('d M Y, h:i A') }}</td>
                                    <td>{{ $payment->method_label }}</td>
                                    <td class="num">{{ money($payment->amount) }}</td>
                                    <td><x-badge :color="$payment->status === 'completed' ? 'success' : 'danger'">{{ ucfirst($payment->status) }}</x-badge></td>
                                    <td class="actions"><a href="{{ route('payments.receipt', $payment) }}" class="btn-icon" title="Receipt"><x-icon name="receipt" :size="15" /></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="table-empty">No payments recorded for this session.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Contact</h3></div></div>
                <div class="card-body">
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value">{{ $student->phone ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">{{ $student->email ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Guardian</span><span class="detail-value">{{ $student->primaryGuardian()?->name ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Guardian phone</span><span class="detail-value">{{ $student->primaryGuardian()?->phone ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Relation</span><span class="detail-value">{{ $student->primaryGuardian()?->relation ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Previous school</span><span class="detail-value">{{ $student->previous_school ?? '—' }}</span></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Portal Accounts</h3><p class="card-sub">Automatic sign-in accounts</p></div></div>
                <div class="card-body">
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Student login</span><span class="detail-value mono">{{ $student->portalLink?->user?->username ?? $student->student_code ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Student status</span><span class="detail-value">{{ $student->portalLink?->user ? ($student->portalLink->is_active ? 'Active' : 'Inactive') : 'Not linked' }}</span></div>
                        @forelse ($student->parentLinks as $link)
                            <div class="detail-row"><span class="detail-label">{{ $link->guardian?->relation ?? 'Guardian' }} login</span><span class="detail-value mono">{{ $link->user?->username ?? '—' }}</span></div>
                        @empty
                            <div class="detail-row"><span class="detail-label">Guardian login</span><span class="detail-value">Not linked</span></div>
                        @endforelse
                    </div>
                    <p class="muted" style="font-size:11.5px;margin:12px 0 0">Passwords are private. The initial password is shown once after admission; it can be reset from the users or portal accounts area.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Attendance</h3><p class="card-sub">{{ $session?->name ?? 'Session' }}</p></div></div>
                <div class="card-body">
                    @if ($attendance->sum() > 0)
                        <div class="kpi-row">
                            <div class="kpi"><div class="kpi-label">Present</div><div class="kpi-value text-success">{{ $attendance['present'] ?? 0 }}</div></div>
                            <div class="kpi"><div class="kpi-label">Absent</div><div class="kpi-value text-danger">{{ $attendance['absent'] ?? 0 }}</div></div>
                            <div class="kpi"><div class="kpi-label">Late</div><div class="kpi-value text-warning">{{ $attendance['late'] ?? 0 }}</div></div>
                            <div class="kpi"><div class="kpi-label">Excused</div><div class="kpi-value">{{ $attendance['excused'] ?? 0 }}</div></div>
                        </div>
                    @else
                        <p class="muted" style="margin:0;font-size:12px">No attendance has been marked for this session.</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Enrolment History</h3></div></div>
                <div class="card-body">
                    @forelse ($student->enrollments->sortByDesc('id') as $row)
                        <div class="due-row">
                            <div class="due-info">
                                <div>
                                    <div class="cell-title">{{ $row->class?->name }} · {{ $row->section?->name }}</div>
                                    <div class="cell-sub">{{ $row->academicSession?->name }} · Roll {{ $row->roll_no }}</div>
                                </div>
                            </div>
                            <x-badge :color="match($row->status) { 'active' => 'success', 'completed' => 'info', 'promoted' => 'violet', 'transferred' => 'warning', default => 'muted' }">{{ ucfirst($row->status) }}</x-badge>
                        </div>
                    @empty
                        <p class="muted" style="font-size:12px;margin:0">No enrolment records.</p>
                    @endforelse
                </div>
            </div>

            @can('students.archive')
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Change Status</h3><p class="card-sub">Archiving preserves all records</p></div></div>
                    <form method="POST" action="{{ route('students.status', $student) }}" onsubmit="return confirm('Update this student status?')">
                        @csrf
                        @method('PATCH')
                        <div class="form-section">
                            <x-select name="status" label="New status" :options="['active' => 'Active', 'inactive' => 'Inactive', 'passed_out' => 'Passed Out', 'transferred' => 'Transferred', 'archived' => 'Archived']" :selected="$student->status" required />
                            <x-input name="remarks" label="Remarks" />
                        </div>
                        <div class="form-section" style="display:flex;justify-content:flex-end">
                            <x-button type="submit" variant="secondary" icon="archive">Update status</x-button>
                        </div>
                    </form>
                </div>
            @endcan
        </div>
    </div>
@endsection
