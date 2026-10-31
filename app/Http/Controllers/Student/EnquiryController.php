<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnquiryRequest;
use App\Models\AdmissionEnquiry;
use App\Models\SchoolClass;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly AuditLogService $audit
    ) {}

    public function index(Request $request): View
    {
        $enquiries = AdmissionEnquiry::query()
            ->with(['schoolClass', 'creator'])
            ->when($request->filled('q'), fn ($query) => $query->where(function ($q) use ($request) {
                $term = $request->string('q');
                $q->where('student_name', 'like', "%{$term}%")
                    ->orWhere('guardian_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('enquiry_no', 'like', "%{$term}%");
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('students.enquiries.index', [
            'enquiries' => $enquiries,
            'counts' => AdmissionEnquiry::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('students.enquiries.create', [
            'classes' => SchoolClass::ordered()->get(),
            'enquiry' => new AdmissionEnquiry(['status' => 'new', 'source' => 'Walk-in']),
        ]);
    }

    public function store(StoreEnquiryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['enquiry_no'] = $this->numbers->enquiryNumber((int) now()->format('Y'));
        $data['created_by'] = $request->user()->id;

        $enquiry = AdmissionEnquiry::create($data);

        $this->audit->record(
            'enquiry_created',
            'Admissions',
            "Admission enquiry {$enquiry->enquiry_no} recorded for {$enquiry->student_name}",
            $enquiry,
            null,
            $enquiry->only(['enquiry_no', 'student_name', 'phone', 'status'])
        );

        return redirect()->route('enquiries.index')->with('success', 'Admission enquiry recorded successfully.');
    }

    public function edit(AdmissionEnquiry $enquiry): View
    {
        return view('students.enquiries.edit', [
            'classes' => SchoolClass::ordered()->get(),
            'enquiry' => $enquiry,
        ]);
    }

    public function update(StoreEnquiryRequest $request, AdmissionEnquiry $enquiry): RedirectResponse
    {
        $before = $enquiry->only(['student_name', 'phone', 'status', 'follow_up_date', 'notes']);
        $enquiry->update($request->validated());

        $this->audit->record(
            'enquiry_updated',
            'Admissions',
            "Admission enquiry {$enquiry->enquiry_no} updated",
            $enquiry,
            $before,
            $enquiry->only(['student_name', 'phone', 'status', 'follow_up_date', 'notes'])
        );

        return redirect()->route('enquiries.index')->with('success', 'Enquiry updated.');
    }

    public function followUp(Request $request, AdmissionEnquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,follow_up,admitted,closed'],
            'follow_up_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = $enquiry->only(['status', 'follow_up_date', 'notes']);
        $enquiry->update($data);

        $this->audit->record(
            'enquiry_follow_up',
            'Admissions',
            "Follow-up logged for enquiry {$enquiry->enquiry_no}",
            $enquiry,
            $before,
            $data
        );

        return back()->with('success', 'Follow-up updated.');
    }
}
