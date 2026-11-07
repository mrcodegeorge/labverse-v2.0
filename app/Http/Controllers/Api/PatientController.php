<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnforcesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    use EnforcesBranchAccess;

    public function index(Request $request): JsonResponse
    {
        $query = Patient::with(['branch:id,code,name', 'registeredByUser:id,name'])
            ->withCount(['orders', 'reports']);

        $this->scopeBranch($query, $request);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('patient_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('other_names', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('nhis_number', 'like', "%{$search}%");
            });
        }

        if ($gender = $request->input('gender')) {
            $query->where('gender', $gender);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $patients = $query->latest()->paginate($perPage);

        return response()->json($patients);
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $phone = trim((string) $request->input('phone', ''));
        $dob = $request->input('date_of_birth');

        if (!$phone && (!$firstName || !$lastName)) {
            return response()->json(['matches' => []]);
        }

        $query = Patient::with('branch:id,code,name');
        $this->scopeBranch($query, $request);

        $query->where(function ($sub) use ($firstName, $lastName, $phone, $dob) {
            if ($phone && strlen($phone) >= 6) {
                $sub->orWhere('phone', $phone);
            }
            if ($firstName && $lastName) {
                $sub->orWhere(function ($nameSub) use ($firstName, $lastName, $dob) {
                    $nameSub->where('first_name', 'like', $firstName)
                        ->where('last_name', 'like', $lastName);
                    if ($dob) {
                        $nameSub->whereDate('date_of_birth', $dob);
                    }
                });
            }
        });

        return response()->json([
            'matches' => $query->limit(5)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'in:Male,Female,Other'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'nhis_number' => ['nullable', 'string', 'max:50'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'genotype' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'force_create' => ['nullable', 'boolean'],
        ]);

        if (!$request->boolean('force_create')) {
            $duplicate = Patient::where('first_name', $validated['first_name'])
                ->where('last_name', $validated['last_name'])
                ->whereDate('date_of_birth', $validated['date_of_birth'])
                ->where('phone', $validated['phone'])
                ->first();

            if ($duplicate) {
                return response()->json([
                    'message' => "A patient record with the same name, date of birth, and phone number already exists ({$duplicate->patient_code}).",
                    'duplicate' => $duplicate,
                ], 422);
            }
        }

        $user = $request->user();
        $branchId = $this->resolveEffectiveBranchId($request, isset($validated['branch_id']) ? (int) $validated['branch_id'] : null)
            ?? $user->branch_id
            ?? 1;

        $nextSeq = (Patient::max('id') ?? 0) + 1;
        $patientCode = sprintf('LV-%s-%04d', date('Y'), $nextSeq);

        $patient = Patient::create([
            ...$validated,
            'patient_code' => $patientCode,
            'branch_id' => $branchId,
            'registered_by' => $user->id,
            'is_active' => true,
        ]);

        $patient->load('branch:id,code,name');

        AuditService::log(
            $request,
            'patient.registered',
            'Patients',
            "Registered new patient {$patient->full_name} ({$patient->patient_code}).",
            'Patient',
            $patient->id,
            $patient->patient_code,
            null,
            $patient->branch_id
        );

        return response()->json([
            'message' => 'Patient registered successfully.',
            'patient' => $patient,
        ], 201);
    }

    public function show(Request $request, Patient $patient): JsonResponse
    {
        $this->authorizeBranchRecord($request, $patient->branch_id);

        $patient->load([
            'branch:id,code,name,city',
            'registeredByUser:id,name,staff_id',
            'orders' => fn ($q) => $q->with([
                'items.test.category',
                'samples',
                'report',
                'branch:id,code,name',
            ])->latest(),
            'reports' => fn ($q) => $q->with(['order:id,order_number', 'approvedByUser:id,name'])->latest(),
            'payments' => fn ($q) => $q->with(['order:id,order_number', 'receivedByUser:id,name'])->latest(),
        ]);

        return response()->json([
            'patient' => $patient,
        ]);
    }

    public function update(Request $request, Patient $patient): JsonResponse
    {
        $this->authorizeBranchRecord($request, $patient->branch_id);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'in:Male,Female,Other'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'nhis_number' => ['nullable', 'string', 'max:50'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'genotype' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
        ]);

        $patient->update($validated);
        $patient->load('branch:id,code,name');

        AuditService::log(
            $request,
            'patient.updated',
            'Patients',
            "Updated demographic profile for {$patient->full_name} ({$patient->patient_code}).",
            'Patient',
            $patient->id,
            $patient->patient_code,
            null,
            $patient->branch_id
        );

        return response()->json([
            'message' => 'Patient information updated successfully.',
            'patient' => $patient,
        ]);
    }
}

