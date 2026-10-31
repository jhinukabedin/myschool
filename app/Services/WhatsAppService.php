<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class WhatsAppService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function isConfigured(): bool
    {
        $provider = SchoolSetting::get('whatsapp_provider', 'none');

        if ($provider === 'meta') {
            return filled(SchoolSetting::get('whatsapp_token'))
                && filled(SchoolSetting::get('whatsapp_phone_number_id'));
        }

        if ($provider === 'generic') {
            return filled(SchoolSetting::get('whatsapp_api_url'))
                && filled(SchoolSetting::get('whatsapp_token'));
        }

        return false;
    }

    public function providerLabel(): string
    {
        return match (SchoolSetting::get('whatsapp_provider', 'none')) {
            'meta' => 'Meta WhatsApp Cloud API',
            'generic' => 'Generic HTTP gateway',
            default => 'Not configured',
        };
    }

    public function sendFeeReminder(Student $student, float $due, AcademicSession $session, User $actor): string
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'whatsapp' => 'WhatsApp is not configured. Add provider credentials under Fee Reminders or Settings before sending messages. No message was sent.',
            ]);
        }

        $guardian = $student->primaryGuardian();
        $phone = preg_replace('/[^0-9]/', '', (string) ($guardian?->phone ?? $student->phone));

        if (! $phone) {
            throw ValidationException::withMessages([
                'whatsapp' => "{$student->full_name} has no guardian phone number.",
            ]);
        }

        $message = "Dear {$guardian?->name}, this is a fee reminder for {$student->full_name}. "
            .'Outstanding dues for session '.$session->name.' are '.money($due).'. '
            .'Please contact the school office to settle the balance. Thank you.';

        $provider = SchoolSetting::get('whatsapp_provider');
        $token = (string) SchoolSetting::get('whatsapp_token');

        if ($provider === 'meta') {
            $url = rtrim((string) SchoolSetting::get('whatsapp_api_url', 'https://graph.facebook.com/v20.0'), '/')
                .'/'.SchoolSetting::get('whatsapp_phone_number_id').'/messages';

            $template = SchoolSetting::get('whatsapp_template_name', 'fee_reminder');

            $response = Http::withToken($token)->timeout(20)->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => SchoolSetting::get('whatsapp_language', 'en')],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $guardian?->name ?? $student->full_name],
                            ['type' => 'text', 'text' => $student->full_name],
                            ['type' => 'text', 'text' => money($due)],
                            ['type' => 'text', 'text' => $session->name],
                        ],
                    ]],
                ],
            ]);
        } else {
            $url = (string) SchoolSetting::get('whatsapp_api_url');
            $response = Http::withToken($token)->timeout(20)->post($url, [
                'to' => $phone,
                'message' => $message,
                'template' => SchoolSetting::get('whatsapp_template_name', 'fee_reminder'),
                'student_id' => $student->id,
                'amount_due' => $due,
                'session' => $session->name,
            ]);
        }

        if (! $response->successful()) {
            $detail = $response->json('error.message') ?? $response->body();
            throw ValidationException::withMessages([
                'whatsapp' => 'WhatsApp provider rejected the message (HTTP '.$response->status().'): '.substr((string) $detail, 0, 300),
            ]);
        }

        $messageId = (string) ($response->json('messages.0.id') ?? $response->json('id') ?? 'accepted');

        $this->audit->record(
            'whatsapp_reminder_sent',
            'Communication',
            "Fee reminder sent to {$student->full_name} at {$phone}",
            $student,
            null,
            ['provider' => $provider, 'message_id' => $messageId, 'amount_due' => $due]
        );

        return $messageId;
    }
}
