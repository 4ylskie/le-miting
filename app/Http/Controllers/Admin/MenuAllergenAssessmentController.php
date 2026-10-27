<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvalidateMenuAllergenAssessmentsRequest;
use App\Http\Requests\Admin\SaveMenuAllergenAssessmentRequest;
use App\Models\Allergen;
use App\Models\MenuItem;
use App\Services\MenuAllergenAssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MenuAllergenAssessmentController extends Controller
{
    public function index(MenuItem $menuItem): View
    {
        return view('admin.menu.allergens.index', [
            'item' => $menuItem, 'allergens' => Allergen::orderBy('sort_order')->get(),
            'assessments' => $menuItem->allergenAssessments()->with('reviewer')->get()->keyBy('allergen_id'),
        ]);
    }

    public function edit(MenuItem $menuItem, Allergen $allergen, MenuAllergenAssessmentService $service): View
    {
        $assessment = $menuItem->allergenAssessments()->with(['reviewer', 'invalidator'])->where('allergen_id', $allergen->id)->first();

        return view('admin.menu.allergens.edit', [
            'item' => $menuItem, 'allergen' => $allergen, 'assessment' => $assessment,
            'context' => $service->context($menuItem, $assessment),
        ]);
    }

    public function update(SaveMenuAllergenAssessmentRequest $request, MenuItem $menuItem, Allergen $allergen, MenuAllergenAssessmentService $service): RedirectResponse
    {
        $service->save($menuItem, $allergen, $request->user(), $request->validated());

        return redirect()->route('admin.menu.allergens.index', $menuItem)->with('status', 'Assessment recorded. This does not establish allergy safety.');
    }

    public function invalidate(InvalidateMenuAllergenAssessmentsRequest $request, MenuItem $menuItem, MenuAllergenAssessmentService $service): RedirectResponse
    {
        $service->invalidate($menuItem, $request->user(), $request->validated('change_type').': '.$request->validated('reason'));

        return back()->with('status', 'Existing assessments invalidated. Both statuses are unknown until reviewed again.');
    }
}
