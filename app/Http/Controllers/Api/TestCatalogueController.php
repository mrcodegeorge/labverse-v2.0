<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabTest;
use App\Models\TestCategory;
use App\Models\TestParameter;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestCatalogueController extends Controller
{
    public function categories(Request $request): JsonResponse
    {
        $categories = TestCategory::withCount('tests')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = LabTest::with(['category:id,code,name,department', 'parameters']);

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('sample_type', 'like', "%{$search}%");
            });
        }

        $tests = $query->orderBy('code')->get();

        return response()->json([
            'tests' => $tests,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:lab_tests,code'],
            'name' => ['required', 'string', 'max:180'],
            'category_id' => ['required', 'exists:test_categories,id'],
            'sample_type' => ['required', 'string', 'max:60'],
            'container_type' => ['nullable', 'string', 'max:80'],
            'sample_volume' => ['nullable', 'string', 'max:40'],
            'price' => ['required', 'numeric', 'min:0'],
            'nhis_tariff' => ['nullable', 'numeric', 'min:0'],
            'turnaround_hours' => ['required', 'integer', 'min:1'],
            'methodology' => ['nullable', 'string', 'max:150'],
            'clinical_indications' => ['nullable', 'string'],
            'preparation_instructions' => ['nullable', 'string'],
            'requires_fasting' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'parameters' => ['nullable', 'array'],
            'parameters.*.code' => ['required', 'string', 'max:40'],
            'parameters.*.name' => ['required', 'string', 'max:120'],
            'parameters.*.input_type' => ['required', 'in:numeric,qualitative,text,select'],
            'parameters.*.unit' => ['nullable', 'string', 'max:40'],
            'parameters.*.ref_min_male' => ['nullable', 'numeric'],
            'parameters.*.ref_max_male' => ['nullable', 'numeric'],
            'parameters.*.ref_min_female' => ['nullable', 'numeric'],
            'parameters.*.ref_max_female' => ['nullable', 'numeric'],
            'parameters.*.critical_low' => ['nullable', 'numeric'],
            'parameters.*.critical_high' => ['nullable', 'numeric'],
            'parameters.*.reference_text' => ['nullable', 'string', 'max:150'],
            'parameters.*.options_json' => ['nullable', 'array'],
        ]);

        $parameters = $validated['parameters'] ?? [];
        unset($validated['parameters']);

        $test = LabTest::create($validated);

        foreach ($parameters as $idx => $param) {
            $test->parameters()->create([
                ...$param,
                'sort_order' => $idx + 1,
            ]);
        }

        $test->load(['category', 'parameters']);

        AuditService::log(
            $request,
            'catalogue.test_created',
            'Test Catalogue',
            "Created diagnostic test {$test->name} ({$test->code}) priced at GHS " . number_format($test->price, 2),
            'LabTest',
            $test->id,
            $test->code
        );

        return response()->json([
            'message' => 'Laboratory test added to catalogue successfully.',
            'test' => $test,
        ], 201);
    }

    public function update(Request $request, LabTest $labTest): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:lab_tests,code,' . $labTest->id],
            'name' => ['required', 'string', 'max:180'],
            'category_id' => ['required', 'exists:test_categories,id'],
            'sample_type' => ['required', 'string', 'max:60'],
            'container_type' => ['nullable', 'string', 'max:80'],
            'sample_volume' => ['nullable', 'string', 'max:40'],
            'price' => ['required', 'numeric', 'min:0'],
            'nhis_tariff' => ['nullable', 'numeric', 'min:0'],
            'turnaround_hours' => ['required', 'integer', 'min:1'],
            'methodology' => ['nullable', 'string', 'max:150'],
            'clinical_indications' => ['nullable', 'string'],
            'preparation_instructions' => ['nullable', 'string'],
            'requires_fasting' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'parameters' => ['nullable', 'array'],
        ]);

        $parameters = $validated['parameters'] ?? null;
        unset($validated['parameters']);

        $labTest->update($validated);

        if (is_array($parameters)) {
            $keepIds = [];
            foreach ($parameters as $idx => $param) {
                if (!empty($param['id'])) {
                    $existing = TestParameter::where('lab_test_id', $labTest->id)->find($param['id']);
                    if ($existing) {
                        $existing->update([
                            'code' => $param['code'],
                            'name' => $param['name'],
                            'input_type' => $param['input_type'] ?? 'numeric',
                            'unit' => $param['unit'] ?? null,
                            'ref_min_male' => $param['ref_min_male'] ?? null,
                            'ref_max_male' => $param['ref_max_male'] ?? null,
                            'ref_min_female' => $param['ref_min_female'] ?? null,
                            'ref_max_female' => $param['ref_max_female'] ?? null,
                            'critical_low' => $param['critical_low'] ?? null,
                            'critical_high' => $param['critical_high'] ?? null,
                            'reference_text' => $param['reference_text'] ?? null,
                            'options_json' => $param['options_json'] ?? null,
                            'sort_order' => $idx + 1,
                        ]);
                        $keepIds[] = $existing->id;
                        continue;
                    }
                }

                $created = $labTest->parameters()->create([
                    'code' => $param['code'],
                    'name' => $param['name'],
                    'input_type' => $param['input_type'] ?? 'numeric',
                    'unit' => $param['unit'] ?? null,
                    'ref_min_male' => $param['ref_min_male'] ?? null,
                    'ref_max_male' => $param['ref_max_male'] ?? null,
                    'ref_min_female' => $param['ref_min_female'] ?? null,
                    'ref_max_female' => $param['ref_max_female'] ?? null,
                    'critical_low' => $param['critical_low'] ?? null,
                    'critical_high' => $param['critical_high'] ?? null,
                    'reference_text' => $param['reference_text'] ?? null,
                    'options_json' => $param['options_json'] ?? null,
                    'sort_order' => $idx + 1,
                ]);
                $keepIds[] = $created->id;
            }
        }

        $labTest->load(['category', 'parameters']);

        AuditService::log(
            $request,
            'catalogue.test_updated',
            'Test Catalogue',
            "Updated diagnostic test {$labTest->name} ({$labTest->code}).",
            'LabTest',
            $labTest->id,
            $labTest->code
        );

        return response()->json([
            'message' => 'Laboratory test configuration updated.',
            'test' => $labTest,
        ]);
    }
}

