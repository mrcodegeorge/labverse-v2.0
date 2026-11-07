<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabReport;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CommunicationService
{
    public static function defaultSettings(): array
    {
        return [
            // Facility defaults
            'facility_name' => 'Labverse Medical Diagnostics & Reference Laboratory',
            'facility_accreditation' => 'ISO 15189:2022 Accredited • HeFRA Reg #GH-LAB-2026-0091',
            'facility_phone' => '+233 302 741 900',
            'facility_email' => 'results@labverse.com',

            // SMTP Email Configuration
            'smtp_enabled' => 'true',
            'smtp_driver' => 'log', // 'smtp' or 'log' (sandbox)
            'smtp_host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'smtp_port' => (string) env('MAIL_PORT', '587'),
            'smtp_encryption' => env('MAIL_ENCRYPTION', 'tls') ?: 'tls',
            'smtp_username' => env('MAIL_USERNAME', ''),
            'smtp_password' => env('MAIL_PASSWORD', ''),
            'smtp_from_address' => env('MAIL_FROM_ADDRESS', 'results@labverse.com'),
            'smtp_from_name' => env('MAIL_FROM_NAME', 'Labverse Medical Diagnostics'),

            // SMS Gateway Configuration
            'sms_enabled' => 'true',
            'sms_provider' => 'sandbox', // sandbox, arkesel, hubtel, mnotify, twilio, termii, custom_webhook
            'sms_api_key' => '',
            'sms_api_secret' => '',
            'sms_sender_id' => 'LABVERSE',
            'sms_webhook_url' => '',

            // Automated Clinical Notification Triggers
            'notify_patient_welcome_sms' => 'true',
            'notify_patient_welcome_email' => 'true',
            'notify_order_placed_sms' => 'true',
            'notify_order_placed_email' => 'true',
            'notify_report_released_sms' => 'true',
            'notify_report_released_email' => 'true',
            'notify_critical_result_sms' => 'true',
            'notify_critical_result_email' => 'true',
            'notify_payment_receipt_sms' => 'true',
            'notify_payment_receipt_email' => 'true',

            // Message Templates
            'tpl_sms_patient_welcome' => 'Welcome to {facility_name}, {patient_name}. Your Medical Record ID is {patient_code}. Please keep this ID for future visits.',
            'tpl_sms_order_placed' => 'Hello {patient_name}, your lab order {order_number} ({test_count} test(s)) has been registered at {facility_name}. Total: GHS {total_amount}.',
            'tpl_sms_report_released' => 'Hello {patient_name}, your official medical laboratory report {report_number} (Order {order_number}) is ready at {facility_name}. Verification Code: {verification_code}.',
            'tpl_sms_payment_receipt' => 'Payment Received: GHS {amount} for Lab Order {order_number} (Receipt: {receipt_number}). Balance Due: GHS {balance_due}. Thank you - {facility_name}.',
            'tpl_sms_critical_result' => 'URGENT CLINICAL ALERT ({facility_name}): Critical diagnostic value detected for {patient_name} ({patient_code}) on {test_name}. Immediate clinical review required.',
        ];
    }

    public static function getConfig(): array
    {
        $defaults = self::defaultSettings();
        if (!Schema::hasTable('system_settings')) {
            return $defaults;
        }

        $stored = SystemSetting::pluck('value', 'key')->toArray();
        return array_merge($defaults, $stored);
    }

    public static function isTruthy(mixed $val): bool
    {
        if (is_bool($val)) {
            return $val;
        }
        return in_array(strtolower(trim((string) $val)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function interpolate(string $template, array $vars): string
    {
        $replacements = [];
        foreach ($vars as $k => $v) {
            $replacements['{' . $k . '}'] = (string) $v;
        }
        return strtr($template, $replacements);
    }

    public static function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/[^\d+]/', '', trim($phone));
        if (str_starts_with($clean, '0') && strlen($clean) === 10) {
            return '+233' . substr($clean, 1);
        }
        if (str_starts_with($clean, '233') && strlen($clean) === 12) {
            return '+' . $clean;
        }
        return $clean;
    }

    /**
     * Send an Email using runtime SMTP configuration or simulated log driver.
     */
    public static function sendEmail(
        string $toEmail,
        string $subject,
        string $bodyText,
        array $context = [],
        ?array $overrideConfig = null
    ): CommunicationLog {
        $cfg = $overrideConfig ? array_merge(self::getConfig(), $overrideConfig) : self::getConfig();
        $driver = strtolower($cfg['smtp_driver'] ?? 'log');
        $status = 'simulated';
        $providerResponse = 'Logged via local sandbox mail driver.';

        $htmlBody = self::buildEmailHtml($subject, $bodyText, $cfg, $context);

        try {
            if ($driver === 'smtp' && !empty($cfg['smtp_host'])) {
                $encryption = strtolower($cfg['smtp_encryption'] ?? 'tls');
                Config::set('mail.default', 'smtp');
                Config::set('mail.mailers.smtp.transport', 'smtp');
                Config::set('mail.mailers.smtp.host', $cfg['smtp_host']);
                Config::set('mail.mailers.smtp.port', (int) ($cfg['smtp_port'] ?? 587));
                Config::set('mail.mailers.smtp.encryption', $encryption === 'none' ? null : $encryption);
                Config::set('mail.mailers.smtp.username', $cfg['smtp_username'] ?: null);
                Config::set('mail.mailers.smtp.password', $cfg['smtp_password'] ?: null);
                Config::set('mail.from.address', $cfg['smtp_from_address'] ?: 'results@labverse.com');
                Config::set('mail.from.name', $cfg['smtp_from_name'] ?: ($cfg['facility_name'] ?? 'LABVERSE LIMS'));

                Mail:: purge('smtp');

                Mail::html($htmlBody, function ($msg) use ($toEmail, $subject, $cfg, $context) {
                    $msg->to($toEmail, $context['recipient_name'] ?? null)
                        ->subject($subject)
                        ->from(
                            $cfg['smtp_from_address'] ?: 'results@labverse.com',
                            $cfg['smtp_from_name'] ?: ($cfg['facility_name'] ?? 'LABVERSE LIMS')
                        );
                });

                $status = 'sent';
                $providerResponse = sprintf(
                    'Delivered via SMTP (%s:%s)',
                    $cfg['smtp_host'],
                    $cfg['smtp_port'] ?? '587'
                );
            } else {
                // Use Laravel log mailer / sandbox simulation so emails work cleanly without external SMTP credentials
                Config::set('mail.default', 'log');
                Mail::html($htmlBody, function ($msg) use ($toEmail, $subject) {
                    $msg->to($toEmail)->subject($subject);
                });
                $status = 'simulated';
                $providerResponse = 'Captured in LABVERSE Mail Sandbox & Laravel Log (Switch SMTP Driver to Live SMTP in Settings for external relay).';
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $providerResponse = 'SMTP Error: ' . $e->getMessage();
        }

        return CommunicationLog::create([
            'branch_id' => $context['branch_id'] ?? null,
            'patient_id' => $context['patient_id'] ?? null,
            'triggered_by' => $context['triggered_by'] ?? null,
            'channel' => 'email',
            'provider' => $driver === 'smtp' ? 'smtp' : 'sandbox',
            'event_type' => $context['event_type'] ?? 'general.email',
            'recipient' => $toEmail,
            'recipient_name' => $context['recipient_name'] ?? null,
            'subject' => $subject,
            'message' => $bodyText,
            'reference_type' => $context['reference_type'] ?? null,
            'reference_code' => $context['reference_code'] ?? null,
            'status' => $status,
            'provider_response' => $providerResponse,
            'sent_at' => now(),
        ]);
    }

    /**
     * Send an SMS via the configured gateway (Arkesel, Hubtel, mNotify, Twilio, Termii, Custom Webhook, or Sandbox).
     */
    public static function sendSms(
        string $toPhone,
        string $message,
        array $context = [],
        ?array $overrideConfig = null
    ): CommunicationLog {
        $cfg = $overrideConfig ? array_merge(self::getConfig(), $overrideConfig) : self::getConfig();
        $provider = strtolower($cfg['sms_provider'] ?? 'sandbox');
        $normalizedPhone = self::normalizePhone($toPhone);
        $senderId = substr(trim($cfg['sms_sender_id'] ?: 'LABVERSE'), 0, 11);
        $apiKey = trim((string) ($cfg['sms_api_key'] ?? ''));
        $apiSecret = trim((string) ($cfg['sms_api_secret'] ?? ''));

        $status = 'simulated';
        $providerResponse = 'Simulated in LABVERSE SMS Sandbox.';

        try {
            if ($provider === 'sandbox' || empty($apiKey) && $provider !== 'custom_webhook') {
                $status = 'simulated';
                $providerResponse = $provider === 'sandbox'
                    ? "Simulated SMS dispatch to {$normalizedPhone} (Sender: {$senderId})."
                    : "Simulated SMS ({$provider} selected but API Key is empty; stored in Communication Outbox).";
            } elseif ($provider === 'arkesel') {
                $res = Http::timeout(10)
                    ->withHeaders(['api-key' => $apiKey])
                    ->post('https://sms.arkesel.com/api/v2/sms/send', [
                        'sender' => $senderId,
                        'message' => $message,
                        'recipients' => [ltrim($normalizedPhone, '+')],
                    ]);
                $status = $res->successful() ? 'sent' : 'failed';
                $providerResponse = 'Arkesel HTTP ' . $res->status() . ': ' . substr($res->body(), 0, 300);
            } elseif ($provider === 'mnotify') {
                $res = Http::timeout(10)
                    ->post('https://api.mnotify.com/api/sms/quick?key=' . urlencode($apiKey), [
                        'recipient' => [ltrim($normalizedPhone, '+')],
                        'sender' => $senderId,
                        'message' => $message,
                        'is_schedule' => false,
                        'schedule_date' => '',
                    ]);
                $status = $res->successful() ? 'sent' : 'failed';
                $providerResponse = 'mNotify HTTP ' . $res->status() . ': ' . substr($res->body(), 0, 300);
            } elseif ($provider === 'hubtel') {
                $res = Http::timeout(10)
                    ->withBasicAuth($apiSecret ?: $apiKey, $apiKey)
                    ->get('https://smsc.hubtel.com/v1/messages/send', [
                        'clientid' => $apiSecret ?: $apiKey,
                        'clientsecret' => $apiKey,
                        'from' => $senderId,
                        'to' => ltrim($normalizedPhone, '+'),
                        'content' => $message,
                    ]);
                $status = $res->successful() ? 'sent' : 'failed';
                $providerResponse = 'Hubtel HTTP ' . $res->status() . ': ' . substr($res->body(), 0, 300);
            } elseif ($provider === 'termii') {
                $res = Http::timeout(10)
                    ->post('https://api.ng.termii.com/api/sms/send', [
                        'to' => ltrim($normalizedPhone, '+'),
                        'from' => $senderId,
                        'sms' => $message,
                        'type' => 'plain',
                        'channel' => 'generic',
                        'api_key' => $apiKey,
                    ]);
                $status = $res->successful() ? 'sent' : 'failed';
                $providerResponse = 'Termii HTTP ' . $res->status() . ': ' . substr($res->body(), 0, 300);
            } elseif ($provider === 'twilio') {
                $accountSid = $apiSecret;
                $res = Http::timeout(10)
                    ->asForm()
                    ->withBasicAuth($accountSid, $apiKey)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                        'From' => $senderId,
                        'To' => $normalizedPhone,
                        'Body' => $message,
                    ]);
                $status = $res->successful() ? 'sent' : 'failed';
                $providerResponse = 'Twilio HTTP ' . $res->status() . ': ' . substr($res->body(), 0, 300);
            } elseif ($provider === 'custom_webhook') {
                $webhookUrl = trim((string) ($cfg['sms_webhook_url'] ?? ''));
                if (empty($webhookUrl)) {
                    throw new \RuntimeException('Custom SMS Webhook URL is not configured.');
                }
                $req = Http::timeout(10);
                if (!empty($apiKey)) {
                    $req = $req->withToken($apiKey);
                }
                $res = $req->post($webhookUrl, [
                    'to' => $normalizedPhone,
                    'sender' => $senderId,
                    'message' => $message,
                    'event' => $context['event_type'] ?? 'general.sms',
                ]);
                $status = $res->successful() ? 'sent' : 'failed';
                $providerResponse = 'Webhook HTTP ' . $res->status() . ': ' . substr($res->body(), 0, 300);
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $providerResponse = 'SMS Gateway Error: ' . $e->getMessage();
        }

        return CommunicationLog::create([
            'branch_id' => $context['branch_id'] ?? null,
            'patient_id' => $context['patient_id'] ?? null,
            'triggered_by' => $context['triggered_by'] ?? null,
            'channel' => 'sms',
            'provider' => $provider,
            'event_type' => $context['event_type'] ?? 'general.sms',
            'recipient' => $normalizedPhone,
            'recipient_name' => $context['recipient_name'] ?? null,
            'subject' => null,
            'message' => $message,
            'reference_type' => $context['reference_type'] ?? null,
            'reference_code' => $context['reference_code'] ?? null,
            'status' => $status,
            'provider_response' => $providerResponse,
            'sent_at' => now(),
        ]);
    }

    // =========================================================================
    // CLINICAL WORKFLOW NOTIFICATION DISPATCHERS
    // =========================================================================

    public static function notifyPatientWelcome(Patient $patient, ?int $userId = null): array
    {
        $cfg = self::getConfig();
        $logs = [];

        $vars = [
            'facility_name' => $cfg['facility_name'] ?? 'LABVERSE Diagnostics',
            'facility_phone' => $cfg['facility_phone'] ?? '',
            'patient_name' => $patient->full_name,
            'patient_code' => $patient->patient_code,
        ];

        $context = [
            'branch_id' => $patient->branch_id,
            'patient_id' => $patient->id,
            'triggered_by' => $userId,
            'recipient_name' => $patient->full_name,
            'event_type' => 'patient.welcome',
            'reference_type' => 'Patient',
            'reference_code' => $patient->patient_code,
        ];

        $smsText = self::interpolate($cfg['tpl_sms_patient_welcome'], $vars);

        if (self::isTruthy($cfg['sms_enabled']) && self::isTruthy($cfg['notify_patient_welcome_sms']) && !empty($patient->phone)) {
            $logs[] = self::sendSms($patient->phone, $smsText, $context);
        }

        if (self::isTruthy($cfg['smtp_enabled']) && self::isTruthy($cfg['notify_patient_welcome_email']) && !empty($patient->email)) {
            $subject = "Welcome to {$vars['facility_name']} — Patient ID: {$patient->patient_code}";
            $emailBody = "Dear {$patient->full_name},\n\n"
                . "Your clinical profile has been registered at {$vars['facility_name']}.\n\n"
                . "• Patient Medical Record ID: {$patient->patient_code}\n"
                . "• Registered Phone: {$patient->phone}\n"
                . "• Gender / DOB: {$patient->gender} ({$patient->date_of_birth?->format('Y-m-d')})\n\n"
                . "Please quote your Patient ID ({$patient->patient_code}) during future laboratory visits.";
            $logs[] = self::sendEmail($patient->email, $subject, $emailBody, $context);
        }

        return $logs;
    }

    public static function notifyOrderPlaced(LabOrder $order, ?int $userId = null): array
    {
        $order->loadMissing(['patient', 'items.test', 'branch']);
        $patient = $order->patient;
        if (!$patient) {
            return [];
        }

        $cfg = self::getConfig();
        $logs = [];
        $testNames = $order->items->map(fn ($i) => $i->test?->name)->filter()->implode(', ');

        $vars = [
            'facility_name' => $cfg['facility_name'] ?? 'LABVERSE Diagnostics',
            'patient_name' => $patient->full_name,
            'patient_code' => $patient->patient_code,
            'order_number' => $order->order_number,
            'test_count' => $order->items->count(),
            'test_list' => $testNames,
            'total_amount' => number_format((float) $order->total_amount, 2),
            'amount_paid' => number_format((float) $order->amount_paid, 2),
            'balance_due' => number_format((float) $order->balance_due, 2),
        ];

        $context = [
            'branch_id' => $order->branch_id,
            'patient_id' => $patient->id,
            'triggered_by' => $userId,
            'recipient_name' => $patient->full_name,
            'event_type' => 'order.placed',
            'reference_type' => 'LabOrder',
            'reference_code' => $order->order_number,
        ];

        $smsText = self::interpolate($cfg['tpl_sms_order_placed'], $vars);

        if (self::isTruthy($cfg['sms_enabled']) && self::isTruthy($cfg['notify_order_placed_sms']) && !empty($patient->phone)) {
            $logs[] = self::sendSms($patient->phone, $smsText, $context);
        }

        if (self::isTruthy($cfg['smtp_enabled']) && self::isTruthy($cfg['notify_order_placed_email']) && !empty($patient->email)) {
            $subject = "Laboratory Test Order Confirmation — {$order->order_number}";
            $emailBody = "Dear {$patient->full_name},\n\n"
                . "Your laboratory test order has been registered at {$vars['facility_name']}.\n\n"
                . "• Order Number: {$order->order_number}\n"
                . "• Patient ID: {$patient->patient_code}\n"
                . "• Ordered Investigations ({$vars['test_count']}): {$testNames}\n"
                . "• Priority: " . strtoupper($order->priority) . "\n"
                . "• Total Amount: GHS {$vars['total_amount']}\n"
                . "• Amount Paid: GHS {$vars['amount_paid']}\n"
                . "• Balance Due: GHS {$vars['balance_due']}\n\n"
                . "You will be notified via SMS/Email as soon as your verified medical report is released.";
            $logs[] = self::sendEmail($patient->email, $subject, $emailBody, $context);
        }

        return $logs;
    }

    public static function notifyReportReleased(
        LabReport $report,
        ?int $userId = null,
        ?array $forceChannels = null,
        ?string $customEmail = null,
        ?string $customPhone = null
    ): array {
        $report->loadMissing(['patient', 'order.items.test', 'order.items.results.parameter', 'branch', 'approvedByUser']);
        $patient = $report->patient;
        if (!$patient) {
            return [];
        }

        $cfg = self::getConfig();
        $logs = [];

        $vars = [
            'facility_name' => $cfg['facility_name'] ?? 'LABVERSE Diagnostics',
            'patient_name' => $patient->full_name,
            'patient_code' => $patient->patient_code,
            'report_number' => $report->report_number,
            'order_number' => $report->order?->order_number ?? '',
            'verification_code' => $report->verification_code,
        ];

        $context = [
            'branch_id' => $report->branch_id,
            'patient_id' => $patient->id,
            'triggered_by' => $userId,
            'recipient_name' => $patient->full_name,
            'event_type' => 'report.released',
            'reference_type' => 'LabReport',
            'reference_code' => $report->report_number,
        ];

        $smsText = self::interpolate($cfg['tpl_sms_report_released'], $vars);
        $targetPhone = $customPhone ?: $patient->phone;
        $targetEmail = $customEmail ?: $patient->email;

        $shouldSendSms = $forceChannels
            ? in_array('sms', $forceChannels, true)
            : (self::isTruthy($cfg['sms_enabled']) && self::isTruthy($cfg['notify_report_released_sms']));

        $shouldSendEmail = $forceChannels
            ? in_array('email', $forceChannels, true)
            : (self::isTruthy($cfg['smtp_enabled']) && self::isTruthy($cfg['notify_report_released_email']));

        if ($shouldSendSms && !empty($targetPhone)) {
            $logs[] = self::sendSms($targetPhone, $smsText, $context);
        }

        if ($shouldSendEmail && !empty($targetEmail)) {
            $subject = "Official Medical Laboratory Report Ready — {$report->report_number}";
            $summaryLines = [];
            foreach ($report->order?->items ?? [] as $item) {
                $summaryLines[] = "Test Panel: " . ($item->test?->name ?? 'Test');
                foreach ($item->results ?? [] as $r) {
                    $flagLabel = strtoupper($r->flag ?? 'NORMAL');
                    $unit = $r->unit ? " {$r->unit}" : '';
                    $ref = $r->reference_range ? " (Ref: {$r->reference_range})" : '';
                    $summaryLines[] = "  - {$r->parameter?->name}: {$r->result_value}{$unit}{$ref} [{$flagLabel}]";
                }
            }

            $emailBody = "Dear {$patient->full_name},\n\n"
                . "Your official diagnostic laboratory report has been verified and released by {$vars['facility_name']}.\n\n"
                . "• Report Number: {$report->report_number}\n"
                . "• Order Number: {$vars['order_number']}\n"
                . "• Patient ID: {$patient->patient_code}\n"
                . "• Verification Hash: {$report->verification_code}\n"
                . "• Approved By: " . ($report->approvedByUser?->name ?? 'Clinical Pathologist') . "\n\n"
                . "Diagnostic Summary:\n" . implode("\n", $summaryLines) . "\n\n"
                . "Pathologist Comment: " . ($report->pathologist_comment ?: 'Verified in accordance with ISO 15189 clinical standards.');

            $logs[] = self::sendEmail($targetEmail, $subject, $emailBody, $context);
        }

        return $logs;
    }

    public static function notifyPaymentReceipt(
        Payment $payment,
        ?int $userId = null,
        ?array $forceChannels = null,
        ?string $customEmail = null,
        ?string $customPhone = null
    ): array {
        $payment->loadMissing(['patient', 'order', 'branch']);
        $patient = $payment->patient;
        if (!$patient) {
            return [];
        }

        $cfg = self::getConfig();
        $logs = [];

        $vars = [
            'facility_name' => $cfg['facility_name'] ?? 'LABVERSE Diagnostics',
            'patient_name' => $patient->full_name,
            'patient_code' => $patient->patient_code,
            'receipt_number' => $payment->receipt_number,
            'order_number' => $payment->order?->order_number ?? '',
            'amount' => number_format((float) $payment->amount, 2),
            'balance_due' => number_format((float) ($payment->order?->balance_due ?? 0), 2),
        ];

        $context = [
            'branch_id' => $payment->branch_id,
            'patient_id' => $patient->id,
            'triggered_by' => $userId,
            'recipient_name' => $patient->full_name,
            'event_type' => 'payment.receipt',
            'reference_type' => 'Payment',
            'reference_code' => $payment->receipt_number,
        ];

        $smsText = self::interpolate($cfg['tpl_sms_payment_receipt'], $vars);
        $targetPhone = $customPhone ?: ($payment->payer_phone ?: $patient->phone);
        $targetEmail = $customEmail ?: $patient->email;

        $shouldSendSms = $forceChannels
            ? in_array('sms', $forceChannels, true)
            : (self::isTruthy($cfg['sms_enabled']) && self::isTruthy($cfg['notify_payment_receipt_sms']));

        $shouldSendEmail = $forceChannels
            ? in_array('email', $forceChannels, true)
            : (self::isTruthy($cfg['smtp_enabled']) && self::isTruthy($cfg['notify_payment_receipt_email']));

        if ($shouldSendSms && !empty($targetPhone)) {
            $logs[] = self::sendSms($targetPhone, $smsText, $context);
        }

        if ($shouldSendEmail && !empty($targetEmail)) {
            $subject = "Official Payment Receipt {$payment->receipt_number} — {$vars['facility_name']}";
            $emailBody = "Dear {$patient->full_name},\n\n"
                . "Thank you for your payment at {$vars['facility_name']}.\n\n"
                . "• Receipt Number: {$payment->receipt_number}\n"
                . "• Invoice Number: {$payment->invoice_number}\n"
                . "• Order Reference: {$vars['order_number']}\n"
                . "• Amount Paid: GHS {$vars['amount']}\n"
                . "• Payment Method: " . strtoupper(str_replace('_', ' ', $payment->payment_method)) . "\n"
                . "• Remaining Balance: GHS {$vars['balance_due']}\n"
                . "• Date: " . ($payment->paid_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'));
            $logs[] = self::sendEmail($targetEmail, $subject, $emailBody, $context);
        }

        return $logs;
    }

    public static function notifyCriticalResult(LabOrderItem $item, ?int $userId = null): array
    {
        $item->loadMissing(['order.patient', 'test', 'order.branch']);
        $patient = $item->order?->patient;
        if (!$patient) {
            return [];
        }

        $cfg = self::getConfig();
        $logs = [];

        $vars = [
            'facility_name' => $cfg['facility_name'] ?? 'LABVERSE Diagnostics',
            'patient_name' => $patient->full_name,
            'patient_code' => $patient->patient_code,
            'order_number' => $item->order?->order_number ?? '',
            'test_name' => $item->test?->name ?? 'Diagnostic Assay',
        ];

        $context = [
            'branch_id' => $item->order?->branch_id,
            'patient_id' => $patient->id,
            'triggered_by' => $userId,
            'recipient_name' => $patient->full_name,
            'event_type' => 'result.critical',
            'reference_type' => 'LabOrder',
            'reference_code' => $item->order?->order_number,
        ];

        $smsText = self::interpolate($cfg['tpl_sms_critical_result'], $vars);

        if (self::isTruthy($cfg['sms_enabled']) && self::isTruthy($cfg['notify_critical_result_sms'])) {
            $alertPhone = $cfg['facility_phone'] ?: $patient->phone;
            if (!empty($alertPhone)) {
                $logs[] = self::sendSms($alertPhone, $smsText, $context);
            }
        }

        if (self::isTruthy($cfg['smtp_enabled']) && self::isTruthy($cfg['notify_critical_result_email'])) {
            $alertEmail = $cfg['facility_email'] ?: ($cfg['smtp_from_address'] ?? 'results@labverse.com');
            if (!empty($alertEmail)) {
                $subject = "[CRITICAL PANIC VALUE] {$vars['test_name']} — {$patient->full_name} ({$patient->patient_code})";
                $logs[] = self::sendEmail($alertEmail, $subject, $smsText, $context);
            }
        }

        return $logs;
    }

    private static function buildEmailHtml(string $subject, string $bodyText, array $cfg, array $context): string
    {
        $facilityName = e($cfg['facility_name'] ?? 'Labverse Medical Diagnostics');
        $accreditation = e($cfg['facility_accreditation'] ?? 'ISO 15189 Clinical Laboratory');
        $facilityPhone = e($cfg['facility_phone'] ?? '');
        $disclaimer = e($cfg['report_footer_disclaimer'] ?? 'Confidential Medical Communication.');
        $safeSubject = e($subject);
        $formattedBody = nl2br(e($bodyText));
        $refBadge = !empty($context['reference_code'])
            ? '<span style="display:inline-block;background:#E0F2FE;color:#164E8A;padding:4px 10px;border-radius:4px;font-family:monospace;font-size:12px;font-weight:700;">REF: ' . e($context['reference_code']) . '</span>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$safeSubject}</title>
</head>
<body style="margin:0;padding:24px;background-color:#F5F7FA;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1E293B;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:8px;overflow:hidden;">
        <tr>
            <td style="background:#164E8A;color:#FFFFFF;padding:20px 24px;">
                <div style="font-size:18px;font-weight:700;letter-spacing:0.3px;">{$facilityName}</div>
                <div style="font-size:12px;color:#BAE6FD;margin-top:4px;">{$accreditation}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <div style="margin-bottom:14px;">{$refBadge}</div>
                <h2 style="margin:0 0 16px 0;font-size:17px;color:#1E293B;">{$safeSubject}</h2>
                <div style="font-size:14px;line-height:1.65;color:#334155;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:16px;">
                    {$formattedBody}
                </div>
            </td>
        </tr>
        <tr>
            <td style="background:#F8FAFC;border-top:1px solid #E2E8F0;padding:16px 24px;font-size:12px;color:#64748B;">
                <div><strong>Clinical Helpdesk:</strong> {$facilityPhone}</div>
                <div style="margin-top:6px;">{$disclaimer}</div>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}

