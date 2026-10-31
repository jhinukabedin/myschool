@extends('layouts.public')

@section('title', 'যোগাযোগ')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / যোগাযোগ</div><h1>যোগাযোগ</h1><p>ঠিকানা, ফোন, ইমেইল ও বার্তা পাঠান</p></div></section>

    <section class="section"><div class="container inner-grid">
        <article class="card article">
            <h2>আমাদের সাথে যোগাযোগ করুন</h2>
            <div class="contact-item"><span>⌖</span><div><b>ঠিকানা</b><span>{{ $siteAddress ?: 'বিদ্যালয় ক্যাম্পাস' }}</span></div></div>
            <div class="contact-item"><span>☎</span><div><b>ফোন</b><span>{{ $sitePhone ?: 'শীঘ্রই প্রকাশিত হবে' }}</span></div></div>
            <div class="contact-item"><span>✉</span><div><b>ইমেইল</b><span>{{ $siteEmail ?: 'শীঘ্রই প্রকাশিত হবে' }}</span></div></div>

            <h3>বার্তা পাঠান</h3>
            <form method="POST" action="{{ route('website.contact.store') }}" class="form-grid" novalidate>
                @csrf
                <input type="text" name="website_url" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

                <div class="field">
                    <label for="name">নাম <span style="color:#a83a3a">*</span></label>
                    <input id="name" name="name" value="{{ old('name') }}" required maxlength="150">
                    @error('name')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="email">ইমেইল</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255">
                    @error('email')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="phone">ফোন</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" maxlength="40" inputmode="tel">
                    @error('phone')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="subject">বিষয়</label>
                    <input id="subject" name="subject" value="{{ old('subject') }}" maxlength="200">
                    @error('subject')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field full">
                    <label for="message">বার্তা <span style="color:#a83a3a">*</span></label>
                    <textarea id="message" name="message" required maxlength="5000">{{ old('message') }}</textarea>
                    @error('message')<small style="color:#a83a3a">{{ $message }}</small>@enderror
                </div>
                <div class="field full">
                    <button type="submit" class="btn btn-purple">বার্তা পাঠান</button>
                </div>
            </form>
        </article>
        <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">অফিস সময়</h3>
                <p>রবি – বৃহস্পতি: সকাল ৮:০০ – বিকাল ৪:০০</p>
                <p>শুক্র ও শনিবার: বন্ধ</p>
            </div>
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">দ্রুত লিংক</h3>
                <a href="{{ route('website.admission') }}" style="display:block;padding:6px 0">ভর্তি তথ্য</a>
                <a href="{{ route('website.notices') }}" style="display:block;padding:6px 0">নোটিশ বোর্ড</a>
                <a href="{{ route('website.about') }}" style="display:block;padding:6px 0">বিদ্যালয় পরিচিতি</a>
            </div>
        </aside>
    </div></section>
@endsection
