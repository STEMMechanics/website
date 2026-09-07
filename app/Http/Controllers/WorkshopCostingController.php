<?php

namespace App\Http\Controllers;

use App\Services\Finance\PricingVersion;
use App\Services\Finance\WorkshopCosting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class WorkshopCostingController extends Controller
{
    public function index(WorkshopCosting $calculator)
    {
        if (! DB::table('finance_pricing_versions')->where('is_snapshot', false)->where('archived', false)->exists()) {
            return redirect()->route('admin.cost-centre.allocations')->with('error', 'Create an active allocation plan to generate workshop costings.');
        }
        $version = PricingVersion::forDate(today()->toDateString());
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
