<?php

namespace App\Http\Controllers;

use App\Services\Finance\GstRate;
use App\Support\RequestMemo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GstRateController extends Controller
{
    public function index(): View
    {
        return view('admin.cost-centre.gst-rates', ['rates' => GstRate::schedule(), 'currentRate' => GstRate::current()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'effective_from' => 'required|date_format:Y-m-d|after:today|unique:finance_gst_rates,effective_from',
            'percentage' => 'required|numeric|gt:0|max:100|decimal:0,2',
            'price_basis' => 'sometimes|required|in:exclusive,inclusive',
        ]);
        DB::table('finance_gst_rates')->insert([
            'effective_from' => $data['effective_from'], 'rate' => round((float) $data['percentage'] / 100, 4), 'price_basis' => $data['price_basis'] ?? 'exclusive',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(RequestMemo::class)->clear();

        return redirect()->route('admin.finance.gst-rates')->with('success', 'GST rate scheduled. Saved quotes, invoices and refunds retain their original tax details.');
    }

    public function destroy(int $gstRate): RedirectResponse
    {
        // Only future entries can be removed; historical tax policy remains available for dated transactions.
        $removed = DB::table('finance_gst_rates')->where('id', $gstRate)->where('effective_from', '>', today()->toDateString())->delete();
        abort_unless($removed > 0, 422, 'Only a future GST rate can be removed.');
        app(RequestMemo::class)->clear();

        return redirect()->route('admin.finance.gst-rates')->with('success', 'Scheduled GST rate removed.');
    }
}
