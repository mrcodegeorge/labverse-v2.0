<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('communication_logs')) {
            Schema::create('communication_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
                $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('channel', 20); // email, sms
                $table->string('provider', 40)->default('sandbox'); // smtp, log, arkesel, hubtel, mnotify, twilio, termii, custom_webhook, sandbox
                $table->string('event_type', 60); // patient.welcome, order.placed, report.released, result.critical, payment.receipt, manual.test
                $table->string('recipient', 160);
                $table->string('recipient_name', 160)->nullable();
                $table->string('subject', 255)->nullable();
                $table->text('message');
                $table->string('reference_type', 60)->nullable();
                $table->string('reference_code', 80)->nullable();
                $table->string('status', 25)->default('sent'); // sent, simulated, failed
                $table->text('provider_response')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};

