<?php

namespace Tests\Feature;

use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Sample;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabverseEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_authentication_and_demo_accounts_work(): void
    {
        $demoResponse = $this->getJson('/api/auth/demo-accounts');
        $demoResponse->assertStatus(200)
            ->assertJsonStructure(['accounts']);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@labverse.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'staff_id', 'permissions', 'roles']]);
    }

    public function test_rbac_enforcement_rejects_unauthorized_actions(): void
    {
        // Receptionist has manage_patients and place_order, but NOT approve_result or manage_users
        $receptionist = User::where('email', 'reception@labverse.com')->firstOrFail();

        $staffRes = $this->actingAs($receptionist, 'sanctum')->getJson('/api/staff');
        $staffRes->assertStatus(403);

        $submittedItem = LabOrderItem::where('status', 'submitted')->firstOrFail();
        $approveRes = $this->actingAs($receptionist, 'sanctum')->postJson("/api/lab/items/{$submittedItem->id}/review", [
            'decision' => 'approve',
        ]);
        $approveRes->assertStatus(403);
    }

    public function test_branch_isolation_restricts_non_admin_staff_to_assigned_branch(): void
    {
        // Receptionist is assigned to Accra Branch (id=1), Patient 5 is in Kumasi Branch (id=2)
        $receptionist = User::where('email', 'reception@labverse.com')->firstOrFail();
        $kumasiPatient = Patient::where('patient_code', 'LV-2026-0005')->firstOrFail();

        // Trying to access Kumasi patient directly as Accra receptionist must return 403
        $showRes = $this->actingAs($receptionist, 'sanctum')->getJson("/api/patients/{$kumasiPatient->id}");
        $showRes->assertStatus(403);

        // Listing patients as Accra receptionist only returns Accra patients
        $listRes = $this->actingAs($receptionist, 'sanctum')->getJson('/api/patients');
        $listRes->assertStatus(200);
        $codes = collect($listRes->json('data'))->pluck('patient_code');
        $this->assertFalse($codes->contains('LV-2026-0005'));
    }

    public function test_complete_end_to_end_clinical_workflow(): void
    {
        $receptionist = User::where('email', 'reception@labverse.com')->firstOrFail();
        $scientist = User::where('email', 'scientist@labverse.com')->firstOrFail();
        $supervisor = User::where('email', 'supervisor@labverse.com')->firstOrFail();
        $cashier = User::where('email', 'cashier@labverse.com')->firstOrFail();

        // 1. Receptionist registers a new patient
        $patRes = $this->actingAs($receptionist, 'sanctum')->postJson('/api/patients', [
            'first_name' => 'Kojo',
            'last_name' => 'Antwi-Boasiako',
            'date_of_birth' => '1989-06-15',
            'gender' => 'Male',
            'phone' => '+233 244 909 808',
            'city' => 'Accra',
        ]);
        $patRes->assertStatus(201);
        $patientId = $patRes->json('patient.id');

        // 2. Receptionist places a test order for Fasting Blood Glucose (FBS-01)
        $fbsTest = LabTest::where('code', 'FBS-01')->firstOrFail();
        $orderRes = $this->actingAs($receptionist, 'sanctum')->postJson('/api/orders', [
            'patient_id' => $patientId,
            'priority' => 'urgent',
            'referring_doctor' => 'Dr. Mensah',
            'test_ids' => [$fbsTest->id],
            'discount_amount' => 5.00,
            'initial_payment_amount' => 15.00,
            'payment_method' => 'mtn_momo',
            'transaction_reference' => 'MOMO-TEST-001',
        ]);
        $orderRes->assertStatus(201);
        $orderId = $orderRes->json('order.id');
        $sampleId = $orderRes->json('order.samples.0.id');
        $orderItemId = $orderRes->json('order.items.0.id');

        // 3. Cashier collects remaining balance
        $payRes = $this->actingAs($cashier, 'sanctum')->postJson('/api/payments', [
            'lab_order_id' => $orderId,
            'amount' => 20.00,
            'payment_method' => 'cash',
        ]);
        $payRes->assertStatus(201);
        $this->assertEquals('paid', $payRes->json('order.payment_status'));

        // 4. Scientist collects and accepts the sample
        $this->actingAs($scientist, 'sanctum')->postJson("/api/samples/{$sampleId}/status", [
            'action' => 'collect',
        ])->assertStatus(200);

        $this->actingAs($scientist, 'sanctum')->postJson("/api/samples/{$sampleId}/status", [
            'action' => 'accept',
            'storage_location' => 'Chemistry Bench 1',
        ])->assertStatus(200);

        // 5. Scientist enters & submits test results (never auto-approved)
        $paramId = $fbsTest->parameters()->first()->id;
        $resSubmit = $this->actingAs($scientist, 'sanctum')->postJson("/api/lab/items/{$orderItemId}/results", [
            'mode' => 'submit',
            'technician_notes' => 'Control normal.',
            'results' => [
                ['test_parameter_id' => $paramId, 'result_value' => '5.1', 'remarks' => 'Normal fasting level'],
            ],
        ]);
        $resSubmit->assertStatus(200);
        $this->assertEquals('submitted', $resSubmit->json('item.status'));

        // 6. Supervisor approves result -> unlocks official medical report
        $revRes = $this->actingAs($supervisor, 'sanctum')->postJson("/api/lab/items/{$orderItemId}/review", [
            'decision' => 'approve',
            'reviewer_comments' => 'Verified normal.',
            'pathologist_comment' => 'Normal fasting plasma glucose.',
        ]);
        $revRes->assertStatus(200);
        $this->assertEquals('approved', $revRes->json('item.status'));

        // 7. Verify report is generated and accessible
        $orderFresh = LabOrder::with('report')->findOrFail($orderId);
        $this->assertNotNull($orderFresh->report);
        $repShow = $this->actingAs($receptionist, 'sanctum')->getJson("/api/reports/{$orderFresh->report->id}");
        $repShow->assertStatus(200)
            ->assertJsonPath('report.status', 'released');

        // 8. Verify manual SMS & Email notification dispatch for the released report
        $notifyRes = $this->actingAs($receptionist, 'sanctum')->postJson("/api/reports/{$orderFresh->report->id}/notify", [
            'channels' => ['sms', 'email'],
            'phone' => '+233 244 909 808',
            'email' => 'kojo.antwi@example.com',
        ]);
        $notifyRes->assertStatus(200);
    }

    public function test_smtp_and_sms_communication_outbox_and_installer_wizard(): void
    {
        $admin = User::where('email', 'admin@labverse.com')->firstOrFail();

        // 1. Test Email dispatch endpoint
        $emailTest = $this->actingAs($admin, 'sanctum')->postJson('/api/communications/test-email', [
            'recipient_email' => 'director@labverse.com',
        ]);
        $emailTest->assertStatus(200)
            ->assertJsonPath('log.channel', 'email');

        // 2. Test SMS dispatch endpoint
        $smsTest = $this->actingAs($admin, 'sanctum')->postJson('/api/communications/test-sms', [
            'recipient_phone' => '0244100001',
        ]);
        $smsTest->assertStatus(200)
            ->assertJsonPath('log.channel', 'sms')
            ->assertJsonPath('log.recipient', '+233244100001');

        // 3. Inspect Communication Outbox logs
        $logsRes = $this->actingAs($admin, 'sanctum')->getJson('/api/communications/logs');
        $logsRes->assertStatus(200)
            ->assertJsonStructure(['logs' => ['data'], 'summary' => ['total', 'sms_count', 'email_count']]);

        // 4. Verify Guided Web Installer Wizard (/install) loads and checks database
        $installPage = $this->get('/install');
        $installPage->assertStatus(200);

        $dbCheck = $this->postJson('/install/check-database', [
            'db_connection' => 'sqlite',
        ]);
        $dbCheck->assertStatus(200)
            ->assertJsonPath('connected', true);
    }
}

