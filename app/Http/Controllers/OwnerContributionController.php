<?php
namespace App\Http\Controllers;
use App\Services\Finance\FinancePlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class OwnerContributionController extends Controller
{
    public function editor(FinancePlanner $planner): \Illuminate\View\View
    {
        $categories = DB::table('finance_categories')->where('active', true)->where('kind', 'cost')->orderBy('priority')->get();
        $balances = $planner->cash()['reserves'];
        return view('admin.timesheet.contribution-editor', compact('categories', 'balances'));
    }
    public function store(Request $request, FinancePlanner $planner): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['token' => 'required|uuid', 'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'amount' => 'required|numeric|min:0.01|max:10000000', 'reference' => 'required|string|max:255', 'splits' => 'required|array|max:100', 'splits.*' => 'required|numeric|min:0|max:10000000']);
        DB::transaction(function () use ($data, $request, $planner) {
            $settings = DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            if (DB::table('finance_owner_contributions')->where('token', $data['token'])->exists()) return;
            if ($settings->opening_date && $data['date'] < $settings->opening_date) throw ValidationException::withMessages(['date' => 'Choose a date on or after the opening balance.']);
            $splits = array_filter(array_map($planner->cents(...), $data['splits']));
            $valid = DB::table('finance_categories')->where('active', true)->where('kind', 'cost')->pluck('id')->all();
            if (array_diff(array_keys($splits), $valid) || array_sum($splits) !== $planner->cents($data['amount'])) throw ValidationException::withMessages(['splits' => 'Allocate the full contribution to active cost centres.']);
            DB::table('finance_owner_contributions')->insert(['user_id' => $request->user()->id, 'token' => $data['token'], 'date' => $data['date'], 'cents' => $planner->cents($data['amount']), 'reference' => $data['reference'], 'splits' => json_encode($splits), 'created_at' => now(), 'updated_at' => now()]);
        });
        return $request->expectsJson() ? response()->json(['message' => 'Contribution recorded.']) : redirect()->route('admin.timesheet.index', ['tab' => 'contributions'])->with('success', 'Contribution recorded.');
    }
}
