@extends('layouts.app')

@section('title', 'Portal Accounts')

@section('content')
    <x-page-header eyebrow="Administration" title="Portal Accounts"
        subtitle="Create and verify student and parent portal accounts. Every portal record is limited to an explicit link.">
        <x-slot:actions>
            <x-button :href="route('admin.index')" variant="secondary" icon="arrow-left">Administration</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="grid-2">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Create or link a portal account</h3><p class="card-sub">A parent account can be linked to several children. Students have one student profile.</p></div></div>
            <form method="POST" action="{{ route('portal-accounts.store') }}">
                @csrf
                <div class="form-section">
                    <div class="form-grid">
                        <div class="field">
                            <label for="portal_role">Portal role</label>
                            <select id="portal_role" name="portal_role" class="input" required>
                                <option value="student" @selected(old('portal_role') === 'student')>Student</option>
                                <option value="parent" @selected(old('portal_role') === 'parent')>Parent / Guardian</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="student_id">Student record</label>
                            <select id="student_id" name="student_id" class="input" required>
                                <option value="">Select a student</option>
                                @foreach ($students as $studentOption)
                                    <option value="{{ $studentOption->id }}" @selected((int) old('student_id') === $studentOption->id)>
                                        {{ $studentOption->full_name }} · Student ID: {{ $studentOption->student_code }}
                                    </option>
                                @endforeach
                                @if ($students->isEmpty())
                                    <option value="" disabled>All active students already have portal accounts.</option>
                                @endif
                            </select>
                        </div>
                        <div class="field">
                            <label for="student_guardian_id">Existing guardian (optional)</label>
                            <select id="student_guardian_id" name="student_guardian_id" class="input">
                                <option value="">No linked guardian row</option>
                                @foreach ($guardians as $guardian)
                                    <option value="{{ $guardian->id }}" @selected((int) old('student_guardian_id') === $guardian->id)>
                                        {{ $guardian->name }} · {{ $guardian->relation }} · student #{{ $guardian->student_id }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="relationship">Relationship</label>
                            <input id="relationship" name="relationship" class="input" value="{{ old('relationship') }}" placeholder="Father, Mother, Guardian">
                        </div>
                        <div class="field">
                            <label for="name">Account name</label>
                            <input id="name" name="name" class="input" value="{{ old('name') }}" required>
                        </div>
                        <div class="field">
                            <label for="email">Email address</label>
                            <input id="email" type="email" name="email" class="input" value="{{ old('email') }}" required>
                        </div>
                        <div class="field">
                            <label for="password">Initial password</label>
                            <input id="password" type="password" name="password" class="input" autocomplete="new-password" placeholder="Required for a new account">
                        </div>
                        <div class="field">
                            <label for="password_confirmation">Confirm password</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" class="input" autocomplete="new-password">
                        </div>
                    </div>
                    <p class="muted" style="font-size:12px">Use a temporary password of at least 10 characters. Ask the user to change it after first sign-in. If the email already belongs to an active account with the selected role and no portal link, the existing account is reused.</p>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary" type="submit">Create / link portal account</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Access rules</h3><p class="card-sub">How portal authorization is enforced</p></div></div>
            <div class="card-body">
                <div class="warning-box" style="margin-bottom:14px">
                    Portal routes require the exact role boundary and an active link. Removing a link immediately blocks access to that student's records, even if the user account remains active.
                </div>
                <ul style="margin:0;padding-left:18px;line-height:1.9;font-size:13px">
                    <li>Students see only their own linked record.</li>
                    <li>Parents see only explicitly linked children.</li>
                    <li>Unpublished results and draft marks are never exposed.</li>
                    <li>Receipts and result sheets re-check ownership on every request.</li>
                    <li>Only school-verified payments count towards a paid balance.</li>
                </ul>
                <p class="muted" style="font-size:12px;margin-bottom:0">Portal URLs: <code>/student/login</code>, <code>/parent/login</code>, <code>/teacher/login</code></p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-header"><div><h3 class="card-title">Student accounts</h3><p class="card-sub">{{ $studentLinks->total() }} linked student account(s)</p></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Student</th><th>Account</th><th>Verified</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($studentLinks as $link)
                        <tr>
                            <td>
                                <div class="table-primary">{{ $link->student?->full_name }}</div>
                                <div class="table-secondary">Student ID: {{ $link->student?->student_code }}</div>
                            </td>
                            <td>
                                <div>{{ $link->user?->name }}</div>
                                <div class="table-secondary">{{ $link->user?->email }}</div>
                            </td>
                            <td>{{ $link->verified_at?->format('d M Y') ?? 'Not verified' }}</td>
                            <td><x-badge :color="$link->is_active ? 'success' : 'muted'">{{ $link->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                            <td>
                                <form method="POST" action="{{ route('portal-accounts.students.destroy', $link) }}" onsubmit="return confirm('Remove this student portal link? The account will lose access immediately.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Unlink</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state">No student portal accounts have been created.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $studentLinks->links() }}</div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-header"><div><h3 class="card-title">Parent accounts</h3><p class="card-sub">{{ $parentLinks->total() }} parent-child link(s). One parent may be linked to several children.</p></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Child</th><th>Parent account</th><th>Relationship</th><th>Verified</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($parentLinks as $link)
                        <tr>
                            <td>
                                <div class="table-primary">{{ $link->student?->full_name }}</div>
                                <div class="table-secondary">Student ID: {{ $link->student?->student_code }}</div>
                            </td>
                            <td>
                                <div>{{ $link->user?->name }}</div>
                                <div class="table-secondary">{{ $link->user?->email }}</div>
                            </td>
                            <td>{{ $link->relationship ?? $link->guardian?->relation ?? '—' }}</td>
                            <td>{{ $link->verified_at?->format('d M Y') ?? 'Not verified' }}</td>
                            <td>
                                <form method="POST" action="{{ route('portal-accounts.parents.destroy', $link) }}" onsubmit="return confirm('Remove this parent link to the child?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Unlink</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state">No parent portal links have been created.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $parentLinks->links() }}</div>
    </div>
@endsection
