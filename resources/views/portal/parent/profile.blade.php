@extends('layouts.portal')

@section('title', 'Guardian Profile')

@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">Parent portal</p>
            <h1>Guardian Profile</h1>
            <p>Your account and the children the school has explicitly linked to it.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('parent.dashboard') }}">Back to dashboard</a>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <div class="card-head"><div><h2>Account</h2><p>Contact the office to change these details</p></div></div>
            <div class="card-body">
                <div class="grid grid-2">
                    <div><div class="small muted">Name</div><strong>{{ $user->name }}</strong></div>
                    <div><div class="small muted">Login username</div><strong class="mono">{{ $user->username ?? '—' }}</strong></div>
                    <div><div class="small muted">Email</div><strong>{{ $user->email ?? '—' }}</strong></div>
                    <div><div class="small muted">Mobile</div><strong>{{ $user->phone ?? '—' }}</strong></div>
                    <div><div class="small muted">Last sign-in</div><strong>{{ $user->last_login_at?->format('d M Y, h:i A') ?? '—' }}</strong></div>
                </div>
                <p style="margin-top:16px"><a class="btn btn-secondary" href="{{ route('password.change') }}">Change password</a></p>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>Linked children</h2><p>Only these students are visible to your account</p></div></div>
            <div class="list">
                @forelse ($children as $child)
                    <div class="list-item">
                        <div>
                            <strong>{{ $child->full_name }}</strong>
                            <small>{{ $child->admission_no }} · {{ $child->status_label }}</small>
                            @php($latest = $child->enrollments->sortByDesc('academic_session_id')->first())
                            @if ($latest)
                                <small>{{ $latest->class?->name }} · Section {{ $latest->section?->name ?? '—' }} · {{ $latest->academicSession?->name }}</small>
                            @endif
                        </div>
                        <a class="btn btn-secondary btn-sm" href="{{ route('parent.dashboard', ['child' => $child->id]) }}">Open</a>
                    </div>
                @empty
                    <div class="empty">No children are linked. Ask the school administration for assistance.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="card" style="margin-top:18px">
        <div class="card-head"><div><h2>School guardian records</h2><p>Relationship details recorded by the school</p></div></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Child</th><th>Relation</th><th>Phone</th><th>Email</th><th>Verified</th></tr></thead>
                <tbody>
                    @forelse ($links as $link)
                        <tr>
                            <td>{{ $link->student?->full_name }}</td>
                            <td>{{ $link->guardian?->relation ?? $link->relationship ?? '—' }}</td>
                            <td>{{ $link->guardian?->phone ?? '—' }}</td>
                            <td>{{ $link->guardian?->email ?? '—' }}</td>
                            <td><span class="badge {{ $link->verified_at ? 'badge-success' : 'badge-warning' }}">{{ $link->verified_at ? 'Verified' : 'Pending' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No guardian links are available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
