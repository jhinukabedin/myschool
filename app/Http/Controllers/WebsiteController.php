<?php

namespace App\Http\Controllers;

use App\Models\AdmissionEnquiry;
use App\Models\ClassRoutine;
use App\Models\ContactMessage;
use App\Models\Examination;
use App\Models\Notice;
use App\Models\ResultSnapshot;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\WebsiteContent;
use App\Services\DocumentNumberService;
use App\Services\ExamResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly ExamResultService $results
    ) {}

    public function home(): View
    {
        $notices = Notice::query()->published()->where('audience', 'all')->latest('publish_at')->limit(5)->get();
        $news = WebsiteContent::query()->ofType('news')->publiclyVisible()->latest('published_at')->limit(3)->get();
        $events = WebsiteContent::query()->ofType('event')->publiclyVisible()->orderBy('published_at')->limit(3)->get();
        $gallery = WebsiteContent::query()->ofType('gallery')->publiclyVisible()->orderBy('sort_order')->limit(6)->get();
        $classes = SchoolClass::query()->ordered()->get();

        $slider = WebsiteContent::query()->ofType('slider')->publiclyVisible()
            ->orderBy('sort_order')->orderBy('id')->get();
        $introduction = $this->firstPublic('introduction');
        $administrationMessages = WebsiteContent::query()->ofType('administration_message')->publiclyVisible()
            ->orderBy('sort_order')->orderByDesc('published_at')->get();
        $administrationMessage = $administrationMessages->first();
        $principalMessage = $this->firstPublic('principal_message');
        $presidentMessage = $this->firstPublic('president_message');
        $academicItems = WebsiteContent::query()->ofType('academic')->publiclyVisible()
            ->orderBy('sort_order')->orderBy('id')->limit(4)->get();

        $stats = [
            'classes' => SchoolClass::count(),
            'notices' => Notice::query()->published()->where('audience', 'all')->count(),
            'news' => WebsiteContent::query()->ofType('news')->publiclyVisible()->count(),
            'events' => WebsiteContent::query()->ofType('event')->publiclyVisible()->count(),
        ];

        return view('website.home', compact(
            'notices', 'news', 'events', 'gallery', 'classes', 'stats', 'slider',
            'introduction', 'administrationMessage', 'administrationMessages', 'principalMessage', 'presidentMessage',
            'academicItems'
        ));
    }

    public function about(): View
    {
        return $this->contentPage('about', 'website.about', [
            'introduction' => $this->firstPublic('introduction'),
        ]);
    }

    public function principalMessage(): View
    {
        return $this->contentPage('principal-message', 'website.principal-message', [
            'message' => $this->firstPublic('principal_message'),
        ]);
    }

    public function administrationMessage(): View
    {
        return $this->messagePage('administration_message', 'administration-message');
    }

    public function administrationMessageItem(string $slug): View
    {
        $message = WebsiteContent::query()
            ->ofType('administration_message')
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('website.message', [
            'message' => $message,
            'page' => null,
            'messageType' => 'administration_message',
        ]);
    }

    public function presidentMessage(): View
    {
        return $this->messagePage('president_message', 'president-message');
    }

    public function academics(): View
    {
        $session = current_session();
        $classes = SchoolClass::query()
            ->with(['subjects' => fn ($query) => $query->with('subject')
                ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))])
            ->ordered()
            ->get();

        return view('website.academics', compact('classes', 'session'));
    }

    public function faculty(): View
    {
        $profiles = WebsiteContent::query()->ofType('faculty')->publiclyVisible()->orderBy('sort_order')->get();

        return view('website.faculty', compact('profiles'));
    }

    public function admission(): View
    {
        return $this->contentPage('admission', 'website.admission', [
            'classes' => SchoolClass::query()->ordered()->get(),
            'admissionItems' => WebsiteContent::query()->ofType('admission_info')->publiclyVisible()->orderBy('sort_order')->get(),
        ]);
    }

    public function admissionApply(): View
    {
        return view('website.admission-apply', [
            'classes' => SchoolClass::query()->ordered()->get(),
        ]);
    }

    public function admissionStore(Request $request): RedirectResponse
    {
        if (filled($request->input('website_url'))) {
            return redirect()->route('website.admission.apply')->with('success', 'Thank you. Your application has been received.');
        }

        $data = $request->validate([
            'student_name' => ['required', 'string', 'max:120'],
            'guardian_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'class_id' => ['nullable', 'exists:classes,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data) {
            $enquiry = AdmissionEnquiry::create([
                'enquiry_no' => $this->numbers->enquiryNumber((int) now()->format('Y')),
                'student_name' => $data['student_name'],
                'guardian_name' => $data['guardian_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'class_id' => $data['class_id'] ?? null,
                'source' => 'Website',
                'status' => 'new',
                'notes' => $data['notes'] ?? null,
                'created_by' => null,
            ]);
        });

        return redirect()->route('website.admission.apply')
            ->with('success', 'Your admission application has been received. Our office will contact you using the phone number provided.');
    }

    public function notices(Request $request): View
    {
        $notices = Notice::query()
            ->published()
            ->where('audience', 'all')
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->string('q').'%'))
            ->latest('publish_at')
            ->paginate(10)
            ->withQueryString();

        return view('website.notices', compact('notices'));
    }

    public function notice(Notice $notice): View
    {
        abort_unless($notice->status === 'published' && $notice->audience === 'all', 404);

        if (($notice->publish_at && $notice->publish_at->isFuture()) || ($notice->expires_at && $notice->expires_at->isPast())) {
            abort(404);
        }

        $related = Notice::query()->published()->where('audience', 'all')
            ->whereKeyNot($notice->id)->latest('publish_at')->limit(4)->get();

        return view('website.notice', compact('notice', 'related'));
    }

    public function news(Request $request): View
    {
        $items = WebsiteContent::query()
            ->ofType('news')
            ->publiclyVisible()
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->string('q').'%'))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('website.news', compact('items'));
    }

    public function newsItem(string $slug): View
    {
        $item = WebsiteContent::query()->ofType('news')->publiclyVisible()->where('slug', $slug)->firstOrFail();
        $related = WebsiteContent::query()->ofType('news')->publiclyVisible()->whereKeyNot($item->id)->latest('published_at')->limit(3)->get();

        return view('website.news-item', compact('item', 'related'));
    }

    public function page(string $slug): View
    {
        $page = WebsiteContent::query()->ofType('page')->publiclyVisible()->where('slug', $slug)->firstOrFail();

        return view('website.page', compact('page'));
    }

    public function events(): View
    {
        $items = WebsiteContent::query()->ofType('event')->publiclyVisible()->orderByDesc('published_at')->paginate(9);

        return view('website.events', compact('items'));
    }

    public function calendar(): View
    {
        $session = current_session();

        $exams = Examination::query()
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->whereIn('status', ['published', 'completed'])
            ->orderBy('start_date')
            ->get();

        $events = WebsiteContent::query()->ofType('event')->publiclyVisible()->orderBy('published_at')->get();

        $calendarItems = WebsiteContent::query()->ofType('academic_calendar')->publiclyVisible()->orderBy('sort_order')->get();

        return view('website.calendar', compact('exams', 'events', 'session', 'calendarItems'));
    }

    public function gallery(): View
    {
        $items = WebsiteContent::query()->ofType('gallery')->publiclyVisible()->orderBy('sort_order')->paginate(12);

        return view('website.gallery', compact('items'));
    }

    public function downloads(): View
    {
        $items = WebsiteContent::query()->ofType('download')->publiclyVisible()->orderBy('sort_order')->paginate(20);

        $syllabusItems = WebsiteContent::query()->ofType('syllabus')->publiclyVisible()->orderBy('sort_order')->get();

        return view('website.downloads', compact('items', 'syllabusItems'));
    }

    public function classRoutine(Request $request): View
    {
        $session = current_session();
        $baseQuery = ClassRoutine::query()->where('is_published', true)
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id));
        $classIds = (clone $baseQuery)->select('class_id')->distinct()->pluck('class_id');
        $classes = SchoolClass::query()->whereIn('id', $classIds)->ordered()->get();
        $selectedClassId = $request->filled('class_id') ? $request->integer('class_id') : null;
        $routines = (clone $baseQuery)->with(['academicSession', 'class', 'section'])
            ->when($selectedClassId, fn ($query) => $query->where('class_id', $selectedClassId))
            ->orderBy('class_id')->orderBy('section_id')->get();

        return view('website.class-routine', compact('classes', 'routines', 'session', 'selectedClassId'));
    }

    public function videoGallery(): View
    {
        $items = WebsiteContent::query()->ofType('video')->publiclyVisible()->orderBy('sort_order')->get();

        return view('website.video-gallery', compact('items'));
    }

    /**
     * Public result search. Only exams that have actually published result
     * snapshots are searchable, and the admin can hide the feed entirely.
     */
    public function results(Request $request): View
    {
        $exams = Examination::query()
            ->where('status', 'published')
            ->whereNotNull('result_published_at')
            ->whereHas('resultSnapshots')
            ->with('academicSession')
            ->withCount('resultSnapshots')
            ->orderByDesc('result_published_at')
            ->get();

        $feedEnabled = school_setting('website_result_search_enabled', '1') === '1';
        $showFeed = $feedEnabled && $exams->isNotEmpty();

        $selectedExam = null;
        $student = null;
        $snapshot = null;
        $result = null;
        $lookup = '';
        $lookupError = null;
        $searched = false;

        if ($showFeed) {
            $examId = $request->integer('exam_id');
            $selectedExam = $exams->firstWhere('id', $examId) ?? $exams->first();
            $lookup = trim($request->string('student_id')->toString());

            if ($lookup !== '') {
                $searched = true;

                $student = Student::query()->where('student_code', $lookup)->first()
                    ?? Student::query()->where('admission_no', $lookup)->first();

                if (! $student) {
                    $lookupError = 'এই আইডি দিয়ে কোনো শিক্ষার্থী পাওয়া যায়নি। আইডি নম্বরটি আবার যাচাই করুন।';
                } else {
                    $snapshot = ResultSnapshot::query()
                        ->where('examination_id', $selectedExam->id)
                        ->where('student_id', $student->id)
                        ->first();

                    if (! $snapshot) {
                        $lookupError = 'এই শিক্ষার্থীর জন্য নির্বাচিত পরীক্ষার ফলাফল এখনো প্রকাশিত হয়নি।';
                    } else {
                        $result = $this->results->resultFromSnapshot($snapshot);
                    }
                }
            }
        }

        return view('website.results', [
            'showFeed' => $showFeed,
            'feedEnabled' => $feedEnabled,
            'exams' => $exams,
            'selectedExam' => $selectedExam,
            'student' => $student,
            'snapshot' => $snapshot,
            'result' => $result,
            'lookup' => $lookup,
            'lookupError' => $lookupError,
            'searched' => $searched,
            'resultTitle' => school_setting('website_result_title') ?: 'রেজাল্ট',
            'resultSubtitle' => school_setting('website_result_subtitle') ?: 'শিক্ষার্থী আইডি দিয়ে ফলাফল খুঁজুন',
            'resultHelp' => school_setting('website_result_help') ?: 'শিক্ষার্থীর আইডি নম্বর লিখুন (যেমন: 2601001)।',
            'resultEmptyMessage' => school_setting('website_result_empty_message') ?: 'এই মুহূর্তে কোনো রেজাল্ট পাবলিশ হয়নি',
        ]);
    }

    public function contact(): View
    {
        return view('website.contact');
    }

    public function contactStore(Request $request): RedirectResponse
    {
        if (filled($request->input('website_url'))) {
            return redirect()->route('website.contact')->with('success', 'Thank you. Your message has been received.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        if (blank($data['email'] ?? null) && blank($data['phone'] ?? null)) {
            return back()->withInput()->withErrors(['email' => 'Provide an email address or a phone number so we can reply.']);
        }

        ContactMessage::create($data + [
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('website.contact')->with('success', 'Thank you for contacting us. The school office will respond as soon as possible.');
    }

    public function privacy(): View
    {
        return $this->contentPage('privacy', 'website.privacy');
    }

    public function sitemap()
    {
        $static = [
            ['url' => route('website.home'), 'priority' => '1.0'],
            ['url' => route('website.about'), 'priority' => '0.8'],
            ['url' => route('website.administration-message'), 'priority' => '0.6'],
            ['url' => route('website.principal-message'), 'priority' => '0.6'],
            ['url' => route('website.president-message'), 'priority' => '0.6'],
            ['url' => route('website.academics'), 'priority' => '0.8'],
            ['url' => route('website.faculty'), 'priority' => '0.7'],
            ['url' => route('website.admission'), 'priority' => '0.9'],
            ['url' => route('website.admission.apply'), 'priority' => '0.9'],
            ['url' => route('website.notices'), 'priority' => '0.8'],
            ['url' => route('website.news'), 'priority' => '0.7'],
            ['url' => route('website.events'), 'priority' => '0.7'],
            ['url' => route('website.calendar'), 'priority' => '0.6'],
            ['url' => route('website.gallery'), 'priority' => '0.6'],
            ['url' => route('website.downloads'), 'priority' => '0.6'],
            ['url' => route('website.class-routine'), 'priority' => '0.6'],
            ['url' => route('website.video-gallery'), 'priority' => '0.6'],
            ['url' => route('website.results'), 'priority' => '0.7'],
            ['url' => route('website.contact'), 'priority' => '0.7'],
            ['url' => route('website.privacy'), 'priority' => '0.3'],
        ];

        $news = WebsiteContent::query()->ofType('news')->publiclyVisible()->get()
            ->map(fn (WebsiteContent $item) => ['url' => route('website.news.item', $item->slug), 'priority' => '0.6']);

        return response()
            ->view('website.sitemap', ['urls' => collect($static)->merge($news)])
            ->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        return response(
            "User-agent: *\nAllow: /\nDisallow: /dashboard\nDisallow: /login\nDisallow: /admin\nDisallow: /students\nDisallow: /staff\nDisallow: /payroll\nDisallow: /fees\nDisallow: /reports\n\nSitemap: ".route('website.sitemap')."\n",
            200,
            ['Content-Type' => 'text/plain']
        );
    }

    /**
     * Render a CMS-managed page when present, otherwise the Blade fallback.
     *
     * @param  array<string, mixed>  $extra
     */
    private function contentPage(string $slug, string $view, array $extra = []): View
    {
        $page = WebsiteContent::query()->ofType('page')->publiclyVisible()->where('slug', $slug)->first();

        return view($view, array_merge($extra, ['page' => $page]));
    }

    private function messagePage(string $type, string $fallbackSlug): View
    {
        return view('website.message', [
            'message' => $this->firstPublic($type),
            'page' => WebsiteContent::query()->ofType('page')->publiclyVisible()->where('slug', $fallbackSlug)->first(),
            'messageType' => $type,
        ]);
    }

    private function firstPublic(string $type): ?WebsiteContent
    {
        return WebsiteContent::query()
            ->ofType($type)
            ->publiclyVisible()
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->first();
    }
}
