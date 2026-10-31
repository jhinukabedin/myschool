<?php

namespace App\Http\Controllers\Fee;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Services\AuditLogService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FeeReminderController extends Controller
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly AuditLogService $audit
    ) {}

    public function index(Request $request): View
    {
        $session = current_session();

        $students = Student::query()
            ->with('guardians')
            ->when($session, fn ($query) => $query->whereHas('feeAssignments', fn ($q) => $q
                ->where('academic_session_id', $session->id)
                ->whereIn('status', ['pending', 'partial'])))
            ->search($request->input('q'))
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        $dues = $session
            ? StudentFeeAssignment::query()
                ->where('academic_session_id', $session->id)
                ->whereIn('student_id', $students->pluck('id'))
                ->whereIn('status', ['pending', 'partial'])
                ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                    ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
                ->get()
                ->groupBy('student_id')
                ->map(fn ($rows) => round($rows->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount)), 2))
            : collect();

        return view('fees.reminders', [
            'students' => $students,
            'dues' => $dues,
            'session' => $session,
            'configured' => $this->whatsapp->isConfigured(),
            'providerLabel' => $this->whatsapp->providerLabel(),
            'settings' => [
                'whatsapp_provider' => SchoolSetting::get('whatsapp_provider', 'none'),
                'whatsapp_api_url' => SchoolSetting::get('whatsapp_api_url', 'https://graph.facebook.com/v20.0'),
                'whatsapp_phone_number_id' => SchoolSetting::get('whatsapp_phone_number_id', ''),
                'whatsapp_template_name' => SchoolSetting::get('whatsapp_template_name', 'fee_reminder'),
                'whatsapp_language' => SchoolSetting::get('whatsapp_language', 'en'),
                'has_token' => filled(SchoolSetting::get('whatsapp_token')),
            ],
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'whatsapp_provider' => ['required', 'in:none,meta,generic'],
            'whatsapp_api_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:120'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:120'],
            'whatsapp_language' => ['nullable', 'string', 'max:10'],
            'whatsapp_token' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('error', 'WhatsApp settings were not saved.');
        }

        $data = $validator->validated();

        foreach (['whatsapp_provider', 'whatsapp_api_url', 'whatsapp_phone_number_id', 'whatsapp_template_name', 'whatsapp_language'] as $key) {
            SchoolSetting::put($key, $data[$key] ?? null, 'whatsapp');
        }

        if (filled($data['whatsapp_token'] ?? null)) {
            SchoolSetting::put('whatsapp_token', $data['whatsapp_token'], 'whatsapp');
        }

        $this->audit->record(
            'whatsapp_settings_updated',
            'Communication',
            'WhatsApp reminder provider settings were updated.',
            null,
            null,
            ['provider' => $data['whatsapp_provider'], 'token_changed' => filled($data['whatsapp_token'] ?? null)]
        );

        return back()->with('success', 'WhatsApp settings saved. Credentials are stored server-side and never shown back in full.');
    }

    public function send(Request $request, Student $student): RedirectResponse
    {
        $session = current_session();
        abort_unless($session, 404);

        $due = (float) StudentFeeAssignment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->whereIn('status', ['pending', 'partial'])
            ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
            ->get()
            ->sum(fn ($row) => max(0, (float) $row->net_amount - (float) $row->paid_amount));

        if ($due <= 0) {
            return back()->with('warning', 'This student has no outstanding dues, so no reminder was sent.');
        }

        try {
            $messageId = $this->whatsapp->sendFeeReminder($student, $due, $session, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'WhatsApp reminder accepted by the provider (message '.$messageId.').');
    }
}
