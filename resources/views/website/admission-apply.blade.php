@extends('layouts.public')

@section('title', 'অনলাইন ভর্তি আবেদন')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / ভর্তি / আবেদন</div><h1>অনলাইন ভর্তি আবেদন</h1><p>নিচের ফরম পূরণ করে আবেদন জমা দিন</p></div></section>

    <section class="section"><div class="container inner-grid">
        <article class="card article">
            <h2>আবেদন ফরম</h2>
            <p>আপনার দেওয়া তথ্য বিদ্যালয় অফিসে ভর্তি অনুসন্ধান হিসেবে সংরক্ষিত হবে। শিক্ষার্থীর ব্যক্তিগত তথ্য শুধুমাত্র ভর্তি প্রক্রিয়ার জন্য ব্যবহৃত হবে।</p>

            <form method="POST" action="{{ route('website.admission.store') }}" class="admission-form" novalidate>
                @csrf
                <input type="text" name="website_url" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

                <div class="field">
                    <label for="student_name">শিক্ষার্থীর নাম <span style="color:#a83a3a">*</span></label>
                    <input id="student_name" name="student_name" value="{{ old('student_name') }}" required maxlength="120">
                    @error('student_name')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="guardian_name">অভিভাবকের নাম <span style="color:#a83a3a">*</span></label>
                    <input id="guardian_name" name="guardian_name" value="{{ old('guardian_name') }}" required maxlength="120">
                    @error('guardian_name')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="phone">মোবাইল নম্বর <span style="color:#a83a3a">*</span></label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" required maxlength="30" inputmode="tel" placeholder="+8801XXXXXXXXX">
                    @error('phone')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="email">ইমেইল (ঐচ্ছিক)</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255">
                    @error('email')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="class_id">আবেদনকৃত ক্লাস</label>
                    <select id="class_id" name="class_id">
                        <option value="">নির্বাচন করুন</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(old('class_id') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                    @error('class_id')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field full">
                    <label for="notes">অতিরিক্ত তথ্য</label>
                    <textarea id="notes" name="notes" maxlength="2000" placeholder="শিক্ষার্থী সম্পর্কে প্রয়োজনীয় তথ্য লিখুন">{{ old('notes') }}</textarea>
                    @error('notes')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field full">
                    <button type="submit" class="btn btn-purple">আবেদন জমা দিন</button>
                </div>
            </form>
        </article>
        <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">জরুরি তথ্য</h3>
                <p>আবেদন জমা দেওয়ার পর বিদ্যালয় অফিস আপনার দেওয়া ফোন নম্বরে যোগাযোগ করবে।</p>
                @if ($sitePhone)<p>ফোন: {{ $sitePhone }}</p>@endif
                @if ($siteEmail)<p>ইমেইল: {{ $siteEmail }}</p>@endif
            </div>
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">প্রয়োজনীয় কাগজপত্র</h3>
                <ul style="padding-left:18px">
                    <li>জন্মনিবন্ধন</li>
                    <li>অভিভাবকের পরিচয়পত্র</li>
                    <li>পূর্ববর্তী ফলাফল</li>
                    <li>ছবি</li>
                </ul>
            </div>
        </aside>
    </div></section>
@endsection
