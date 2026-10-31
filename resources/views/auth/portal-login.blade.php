<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} Login · {{ school_setting('school_name', 'School') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('portal/style.css') }}">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; background: radial-gradient(circle at 12% 8%, #1d3c68 0, #12233f 42%, #0c1930 100%); padding: 24px; }
        .login-shell { width: min(980px, 100%); display: grid; grid-template-columns: 1.1fr .9fr; border-radius: 24px; overflow: hidden; box-shadow: 0 30px 80px rgba(2, 10, 25, .45); border: 1px solid rgba(255,255,255,.12); }
        .login-intro { padding: clamp(28px, 5vw, 52px); color: #fff; background: linear-gradient(145deg, rgba(255,255,255,.07), rgba(255,255,255,.015)); }
        .login-intro .brand-mark { width: 58px; height: 58px; font-size: 19px; margin-bottom: 28px; }
        .login-intro h1 { margin: 0 0 12px; font-size: clamp(28px, 4vw, 42px); line-height: 1.1; letter-spacing: -.03em; }
        .login-intro p { color: #c7d4e8; max-width: 430px; }
        .login-intro ul { list-style: none; padding: 0; margin: 28px 0 0; display: grid; gap: 10px; color: #dbe6f5; font-size: 14px; }
        .login-intro li::before { content: '✓'; color: #8fd8c9; font-weight: 800; margin-right: 9px; }
        .login-form { background: #fff; padding: clamp(28px, 5vw, 48px); display: flex; flex-direction: column; justify-content: center; }
        .login-form h2 { margin: 0 0 4px; font-size: 25px; }
        .login-form .muted { margin: 0 0 24px; }
        .login-form form { display: grid; gap: 14px; }
        .login-form .field input { width: 100%; }
        .login-form .btn { width: 100%; min-height: 44px; }
        .remember { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475467; }
        .remember input { min-height: auto; }
        .login-links { display: flex; flex-wrap: wrap; gap: 8px 16px; margin-top: 20px; font-size: 12px; }
        .login-links a { color: #475467; text-decoration: none; }
        .login-links a:hover { color: var(--navy); text-decoration: underline; }
        .alert { margin-bottom: 16px; }
        @media (max-width: 760px) { .login-shell { grid-template-columns: 1fr; } .login-intro { display: none; } }
    </style>
</head>
<body>
    <main class="login-shell">
        <section class="login-intro">
            <span class="brand-mark">{{ school_setting('website_logo_mark', 'HS') }}</span>
            <p class="eyebrow" style="color:#8fd8c9">{{ $title }}</p>
            <h1>{{ school_setting('website_name_bn', 'Adarsha High School') }}</h1>
            <p>One secure sign-in for the school's student, guardian and teacher services. Your account only opens records the school has linked to you.</p>
            <ul>
                <li>Published results and grade sheets</li>
                <li>Attendance and fee ledger</li>
                <li>Verified payment receipts</li>
                <li>Downloadable school resources</li>
            </ul>
        </section>
        @php
            $identifierLabel = match ($portal) {
                'student' => 'Student ID',
                'parent' => 'Guardian mobile number',
                default => 'Email or username',
            };
            $identifierPlaceholder = match ($portal) {
                'student' => 'e.g. 202601001',
                'parent' => 'e.g. 01712345678',
                default => 'you@school.test or username',
            };
            $identifierHelp = match ($portal) {
                'student' => 'Use the permanent student ID printed on the admission slip.',
                'parent' => 'Use the mobile number recorded at admission. One account works for all linked children.',
                default => 'Your school office can provide your username if you do not have an email address.',
            };
        @endphp
        <section class="login-form">
            <h2>Sign in</h2>
            <p class="muted">{{ $title }} account</p>

            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <strong>Sign-in failed</strong>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ $action }}">
                @csrf
                <div class="field">
                    <label for="email">{{ $identifierLabel }}</label>
                    <input id="email" type="text" name="email" value="{{ old('email') }}" autocomplete="username" placeholder="{{ $identifierPlaceholder }}" required autofocus>
                    <span class="field-hint">{{ $identifierHelp }}</span>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <label class="remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in on this device</label>
                <button class="btn btn-primary" type="submit">Sign in to {{ $title }}</button>
            </form>

            <div class="login-links">
                <a href="{{ route('password.request') }}">Forgot password?</a>
                @if ($portal !== 'student')<a href="{{ route('student.login') }}">Student portal</a>@endif
                @if ($portal !== 'parent')<a href="{{ route('parent.login') }}">Parent portal</a>@endif
                @if ($portal !== 'teacher')<a href="{{ route('teacher.login') }}">Teacher portal</a>@endif
                <a href="{{ route('website.home') }}">School website</a>
                <a href="{{ route('login') }}">Staff login</a>
            </div>
        </section>
    </main>
</body>
</html>
