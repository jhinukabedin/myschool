@extends('layouts.app')

@section('title', $staffMember->full_name)

@php
    $lastPayroll = $staffMember->payrolls->first();
    $paidTotal = $staffMember->payrolls->where('status', 'paid')->sum('net_salary');
@endphp

@section('content')
    <x-page-header eyebrow="Staff" :title="$staffMember->full_name" :subtitle="($staffMember->employee_no ?? '').' · '.($staffMember->designation?->name ?? 'No designation').' · '.($staffMember->department?->name ?? 'No department')">
        <x-slot:actions>
            @can('staff.manage')
                <x-button :href="route('staff.edit', $staffMember)" variant="secondary" icon="edit">Edit profile</x-button>
            @endcan
            @can('payroll.view')
                <x-button :href="route('payroll.index')" variant="primary" icon="banknote">Salary management</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-stat-card label="Basic salary" :value="money($staffMember->basic_salary)" icon="wallet" tone="student" meta="Employment contract" />
        <x-stat-card label="Current net" :value="$currentStructure ? money($currentStructure->net_salary) : '—'" icon="banknote" tone="collection" :meta="$currentStructure ? 'Effective '.$currentStructure->effective_from?->format('d M Y') : 'No structure'" />
        <x-stat-card label="Payrolls" :value="number_format($staffMember->payrolls->count())" icon="file-text" tone="expense" meta="All months" />
        <x-stat-card label="Paid total" :value="money($paidTotal)" icon="check-circle" tone="pending" :meta="$lastPayroll ? 'Latest '.$lastPayroll->month : 'Not paid yet'" />
    </div>

    <div class="grid-main-side">
        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Employee Profile</h3></div><x-badge :color="$staffMember->status === 'active' ? 'success' : 'muted'">{{ ucfirst($staffMember->status) }}</x-badge></div>
                <div class="card-body">
                    <div class="grid-2">
                        <div class="detail-list">
                            <div class="detail-row"><span class="detail-label">Employee no</span><span class="detail-value mono">{{ $staffMember->employee_no }}</span></div>
                            <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value">{{ ucfirst($staffMember->gender) }}</span></div>
                            <div class="detail-row"><span class="detail-label">Date of birth</span><span class="detail-value">{{ $staffMember->date_of_birth?->format('d M Y') ?? '—' }}</span></div>
                            <div class="detail-row"><span class="detail-label">Joined</span><span class="detail-value">{{ $staffMember->joining_date?->format('d M Y') }}</span></div>
                            <div class="detail-row"><span class="detail-label">Employment</span><span class="detail-value">{{ ucfirst(str_replace('_', ' ', $staffMember->employment_type)) }}</span></div>
                        </div>
                        <div class="detail-list">
                            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value">{{ $staffMember->phone }}</span></div>
                            <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">{{ $staffMember->email ?? '—' }}</span></div>
                            <div class="detail-row"><span class="detail-label">Address</span><span class="detail-value">{{ $staffMember->address ?? '—' }}</span></div>
                            <div class="detail-row"><span class="detail-label">Department</span><span class="detail-value">{{ $staffMember->department?->name ?? '—' }}</span></div>
                            <div class="detail-row"><span class="detail-label">Designation</span><span class="detail-value">{{ $staffMember->designation?->name ?? '—' }}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Payroll History</h3><p class="card-sub">Most recent salary rows</p></div></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Month</th><th class="num">Basic</th><th class="num">Allowances</th><th class="num">Deductions</th><th class="num">Net</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($staffMember->payrolls->take(12) as $payroll)
                                <tr>
                                    <td class="cell-title">{{ $payroll->month }}</td>
                                    <td class="num">{{ money($payroll->basic_salary) }}</td>
                                    <td class="num text-success">{{ money($payroll->total_allowances) }}</td>
                                    <td class="num text-danger">{{ money($payroll->total_deductions) }}</td>
                                    <td class="num">{{ money($payroll->net_salary) }}</td>
                                    <td><x-badge :color="$payroll->status === 'paid' ? 'success' : ($payroll->status === 'processed' ? 'info' : 'warning')">{{ ucfirst($payroll->status) }}</x-badge></td>
                                    <td class="actions">
                                        @can('payroll.view')
                                            <a href="{{ route('payroll.slip', $payroll) }}" class="btn-icon" title="Salary slip"><x-icon name="receipt" :size="15" /></a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="table-empty">No payroll rows have been generated for this staff member.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Portal Login</h3><p class="card-sub">Staff sign-in account</p></div></div>
                <div class="card-body">
                    @if ($staffMember->user)
                        <div class="detail-list">
                            <div class="detail-row"><span class="detail-label">Username</span><span class="detail-value mono">{{ $staffMember->user->username }}</span></div>
                            <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">{{ $staffMember->user->email ?? '—' }}</span></div>
                            <div class="detail-row"><span class="detail-label">Role</span><span class="detail-value">{{ $staffMember->user->role?->name ?? '—' }}</span></div>
                            <div class="detail-row"><span class="detail-label">Status</span><x-badge :color="$staffMember->user->is_active ? 'success' : 'muted'">{{ $staffMember->user->is_active ? 'Active' : 'Inactive' }}</x-badge></div>
                        </div>
                        <p class="muted" style="font-size:11.5px;margin:12px 0 0">Passwords are private. Use “Edit profile” to change the role or username; reset the password from the users area if needed.</p>
                    @else
                        <p class="muted" style="font-size:12px;margin:0">No login account is linked yet. Edit this profile to create one.</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Salary Structure</h3><p class="card-sub">Latest effective structure</p></div></div>
                <div class="card-body">
                    @if ($currentStructure)
                        <div class="detail-list">
                            <div class="detail-row"><span class="detail-label">Effective from</span><span class="detail-value">{{ $currentStructure->effective_from?->format('d M Y') }}</span></div>
                            <div class="detail-row"><span class="detail-label">Basic</span><span class="detail-value">{{ money($currentStructure->basic_salary) }}</span></div>
                            <div class="detail-row"><span class="detail-label">Allowances</span><span class="detail-value text-success">{{ money($currentStructure->total_allowances) }}</span></div>
                            <div class="detail-row"><span class="detail-label">Deductions</span><span class="detail-value text-danger">{{ money($currentStructure->total_deductions) }}</span></div>
                            <div class="divider" style="margin:10px 0"></div>
                            <div class="detail-row"><span class="detail-label">Net salary</span><span class="detail-value" style="font-size:15px">{{ money($currentStructure->net_salary) }}</span></div>
                        </div>
                    @else
                        <p class="muted" style="font-size:12px;margin:0">No salary structure exists. Payroll cannot be generated until one is added.</p>
                    @endif
                </div>
            </div>

            @can('payroll.manage')
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Add Salary Structure</h3><p class="card-sub">Use a new effective date to preserve history</p></div></div>
                    <form method="POST" action="{{ route('staff.salary-structure.store', $staffMember) }}">
                        @csrf
                        <div class="form-section">
                            <div class="stack">
                                <div class="form-grid">
                                    <x-input name="basic_salary" label="Basic salary" type="number" step="0.01" min="0" :value="$currentStructure?->basic_salary" required />
                                    <x-input name="effective_from" label="Effective from" type="date" :value="now()->toDateString()" required />
                                </div>
                                <div class="form-grid">
                                    <x-input name="house_allowance" label="House" type="number" step="0.01" min="0" :value="$currentStructure?->house_allowance" />
                                    <x-input name="transport_allowance" label="Transport" type="number" step="0.01" min="0" :value="$currentStructure?->transport_allowance" />
                                    <x-input name="medical_allowance" label="Medical" type="number" step="0.01" min="0" :value="$currentStructure?->medical_allowance" />
                                    <x-input name="other_allowance" label="Other allowance" type="number" step="0.01" min="0" :value="$currentStructure?->other_allowance" />
                                    <x-input name="tax_deduction" label="Tax" type="number" step="0.01" min="0" :value="$currentStructure?->tax_deduction" />
                                    <x-input name="provident_fund" label="Provident fund" type="number" step="0.01" min="0" :value="$currentStructure?->provident_fund" />
                                    <x-input name="other_deduction" label="Other deduction" type="number" step="0.01" min="0" :value="$currentStructure?->other_deduction" />
                                </div>
                                <x-button type="submit" variant="primary" icon="save" class="btn-block">Save structure</x-button>
                            </div>
                        </div>
                    </form>
                </div>
            @endcan

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Structure History</h3></div></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Effective</th><th class="num">Net</th></tr></thead>
                        <tbody>
                            @forelse ($staffMember->salaryStructures->sortByDesc('effective_from')->take(8) as $structure)
                                <tr>
                                    <td>{{ $structure->effective_from?->format('d M Y') }}</td>
                                    <td class="num">{{ money($structure->net_salary) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="table-empty">No history.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
