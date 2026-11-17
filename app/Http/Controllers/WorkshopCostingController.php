<?php

namespace App\Http\Controllers;

use App\Services\Finance\PricingVersion;
use App\Services\Finance\WorkshopCosting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkshopCostingController extends Controller
{
    public function index()
    {
        $versions = DB::table('finance_pricing_versions')->where('is_snapshot', false)->where('archived', false)->orderByDesc('effective_from')->orderByDesc('id')->get(['id', 'name']);
        if ($versions->isEmpty()) {
            return redirect()->route('admin.cost-centre.allocations')->with('error', 'Create an active allocation plan to generate workshop costings.');
        }

        $defaultVersionId = (int) DB::table('finance_settings')->where('id', 1)->value('default_pricing_version_id');
        if (! $versions->contains(fn ($version) => (int) $version->id === $defaultVersionId)) {
            $defaultVersionId = (int) $versions->first()->id;
        }

        return view('admin.workshop-costing.select', compact('versions', 'defaultVersionId'));
    }

    public function pdf(Request $request, WorkshopCosting $calculator)
    {
        $activeVersions = DB::table('finance_pricing_versions')->where('is_snapshot', false)->where('archived', false);
        if (! (clone $activeVersions)->exists()) {
            return redirect()->route('admin.cost-centre.allocations')->with('error', 'Create an active allocation plan to generate workshop costings.');
        }
        $validated = $request->validate([
            'version_id' => ['nullable', 'integer', Rule::exists('finance_pricing_versions', 'id')->where('is_snapshot', false)->where('archived', false)],
        ]);
        $version = isset($validated['version_id'])
            ? (clone $activeVersions)->where('id', $validated['version_id'])->firstOrFail()
            : PricingVersion::forDate(today()->toDateString());
        $data = $calculator->guide($version, DB::table('finance_categories')->pluck('name', 'id')->all());
        // Keep the reference sheet on one page as cost-centre names and rules change.
        foreach ([10, 9, 8, 7] as $fontSize) {
            $pdf = Pdf::loadView('pdf.workshop-costing', $data + ['fontSize' => $fontSize])->setPaper('a4');
            $pdf->render();
            if ($pdf->getDomPDF()->getCanvas()->get_page_count() === 1) {
                return $pdf->stream('workshop-costings.pdf');
            }
        }
        abort(422, 'The allocation plan has too many cost details for a readable one-page guide.');
    }
}
