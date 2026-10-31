@extends('layouts.portal')

@section('title', 'Study Materials')
@section('banner-subtitle', 'বিষয়ভিত্তিক নোট, PDF ও শিক্ষাসামগ্রী')

@section('content')
    <form class="portal-filter" method="GET" action="{{ route('student.materials') }}">
        <div class="field">
            <label for="material-category">বিষয় / শ্রেণি</label>
            <select id="material-category" name="category">
                <option value="">সব বিষয়</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="material-q">ফাইল খুঁজুন</label>
            <input id="material-q" name="q" value="{{ request('q') }}" placeholder="ফাইল খুঁজুন...">
        </div>
        <div class="field" style="align-self:end">
            <button class="btn btn-purple" type="submit">খুঁজুন</button>
        </div>
        @if (request()->filled('category') || request()->filled('q'))
            <div class="field" style="align-self:end">
                <a class="btn btn-outline" href="{{ route('student.materials') }}">রিসেট</a>
            </div>
        @endif
    </form>

    <div class="portal-panel">
        <h2>শিক্ষাসামগ্রী</h2>
        <div class="portal-panel-body">
            <div class="download-list">
                @forelse ($materials as $material)
                    <a href="{{ $material->file_url ?: route('student.downloads') }}" @if ($material->file_url) target="_blank" rel="noopener" @endif>
                        <span>
                            <b>{{ $material->title }}</b>
                            <small style="display:block;color:#7a8592">
                                {{ $material->category ?: 'সাধারণ' }} · {{ $material->type === 'syllabus' ? 'সিলেবাস' : 'ডাউনলোড' }}
                                @if ($material->excerpt) · {{ $material->excerpt }} @endif
                            </small>
                        </span>
                        <b>{{ $material->file_url ? '↓' : '→' }}</b>
                    </a>
                @empty
                    <div class="empty">
                        <strong>কোনো শিক্ষাসামগ্রী পাওয়া যায়নি।</strong><br>
                        শিক্ষক বা স্কুল অফিস শিক্ষাসামগ্রী প্রকাশ করলে এখানে দেখা যাবে।
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
