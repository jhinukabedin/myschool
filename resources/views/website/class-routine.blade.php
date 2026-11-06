@extends('layouts.public')

@section('title', 'ক্লাস রুটিন')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / একাডেমিক / ক্লাস রুটিন</div><h1>ক্লাস রুটিন</h1><p>শ্রেণি ও শাখা অনুযায়ী প্রকাশিত ক্লাস রুটিন</p></div></section>

    <section class="section"><div class="container">
        <div class="card article" style="padding:clamp(20px,4vw,36px);border-radius:18px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:22px">
                <div><h2 style="margin:0 0 5px">প্রকাশিত রুটিন</h2>
                    <p class="muted" style="margin:0">{{ $session?->name ? $session->name.' শিক্ষাবর্ষ' : 'সকল শিক্ষাবর্ষ' }}</p></div>
                <form method="GET" action="{{ route('website.class-routine') }}" style="display:flex;align-items:end;gap:10px;flex-wrap:wrap">
                    <label style="display:grid;gap:6px;font-size:13px;font-weight:600">শ্রেণি
                        <select name="class_id" class="form-control" style="min-width:190px;padding:10px 12px;border:1px solid #d8deea;border-radius:9px">
                            <option value="">সব শ্রেণি</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected((string) $selectedClassId === (string) $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn btn-purple">দেখুন</button>
                    @if ($selectedClassId)<a class="btn btn-outline" href="{{ route('website.class-routine') }}">সব দেখুন</a>@endif
                </form>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px">
                @forelse ($routines as $routine)
                    <article style="border:1px solid #e3e8f0;border-radius:14px;padding:20px;background:linear-gradient(145deg,#fff,#f8faff);box-shadow:0 8px 22px rgba(25,45,80,.06)">
                        <div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
                            <div><span style="display:inline-block;padding:4px 9px;border-radius:99px;background:#edf2ff;color:#334d86;font-size:12px;font-weight:700">{{ $routine->academicSession?->name ?? 'শিক্ষাবর্ষ' }}</span>
                                <h3 style="margin:13px 0 4px;font-size:21px">{{ $routine->class?->name }}</h3>
                                <p class="muted" style="margin:0">শাখা: {{ $routine->section?->name ?? '-' }}</p>
                            </div>
                            <span aria-hidden="true" style="font-size:24px;color:#506b9d">▦</span>
                        </div>
                        <div style="border-top:1px solid #e8ecf2;margin:16px 0 14px;padding-top:12px;font-size:13px;color:#596579">কার্যকর: {{ $routine->effective_from?->format('d M Y') ?? '-' }}</div>
                        <div style="display:flex;gap:9px;flex-wrap:wrap">
                            <button type="button" class="btn btn-outline" style="flex:1;justify-content:center" data-routine-preview data-preview-url="{{ route('website.class-routine.show', ['routine' => $routine, 'embed' => 1]) }}" data-preview-pdf-url="{{ route('website.class-routine.pdf', $routine) }}">রুটিন দেখুন</button>
                            <a class="btn btn-purple" style="flex:1;justify-content:center" href="{{ route('website.class-routine.pdf', $routine) }}">PDF ডাউনলোড</a>
                        </div>
                    </article>
                @empty
                    <div style="grid-column:1/-1;text-align:center;padding:40px 20px;border:1px dashed #ccd5e3;border-radius:14px">
                        <h3 style="margin:0 0 8px">কোনো প্রকাশিত রুটিন নেই</h3>
                        <p class="muted" style="margin:0">প্রশাসনিক প্যানেল থেকে রুটিন প্রকাশ করা হলে এখানে দেখা যাবে।</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div></section>
    @include('academics.routines._preview-modal')
@endsection
