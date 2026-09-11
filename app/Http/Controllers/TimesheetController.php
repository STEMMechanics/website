<?php

namespace App\Http\Controllers;

use App\Services\Finance\FinancePlanner;
use App\Support\ListPageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TimesheetController extends Controller
{
    public function index(Request $request, FinancePlanner $planner): View
    {
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['time', 'drawings', 'contributions'])]])['tab'] ?? 'time';
        if ($tab === 'contributions') {
            $contributions = DB::table('finance_owner_contributions')->where('user_id', $request->user()->id)->orderByDesc('date')->orderByDesc('id')->paginate(ListPageSize::resolve(25))->withQueryString();
            $categories = DB::table('finance_categories')->pluck('name', 'id');
            return view('admin.timesheet.index', compact('tab', 'contributions', 'categories'));
        }
        if ($tab === 'drawings') {
            $user = $request->user()->id;
            $cash = $planner->cash();
            $forgone = $planner->remunerationForgone($user);
            $earned = $planner->earned($user) - $forgone;
            $remunerationTransfers = DB::table('finance_fund_transfers')->join('finance_categories', 'finance_categories.id', '=', 'finance_fund_transfers.category_id')->where('remuneration_user_id', $user)->select('finance_fund_transfers.*', 'finance_categories.name as centre_name')->orderByDesc('finance_fund_transfers.id')->get();
            $query = DB::table('finance_drawings')->where('user_id', $user);
            $purpose = $request->validate(['purpose' => ['nullable', Rule::in(['time', 'contribution'])]])['purpose'] ?? 'time';
            if ($purpose === 'contribution') $earned = (int) DB::table('finance_owner_contributions')->where('user_id', $user)->sum('cents');
            $paid = (int) (clone $query)->where('purpose', $purpose)->where('status', 'paid')->sum('cents');
            $pending = (int) (clone $query)->where('purpose', $purpose)->where('status', 'pending')->sum('cents');
            $drawingTotals = $planner->drawingTotals($user, $cash);
            $drawings = $query->where('purpose', $purpose)->orderByDesc('id')->paginate(ListPageSize::resolve(25))->withQueryString();

            return view('admin.timesheet.index', compact('tab', 'cash', 'earned', 'paid', 'pending', 'drawings', 'purpose', 'drawingTotals', 'forgone', 'remunerationTransfers'));
        }
        $data = $request->validate(['fortnight' => 'nullable|date_format:Y-m-d', 'sort' => ['nullable', Rule::in(['date', 'minutes'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $anchor = Carbon::parse(\App\Models\SiteOption::value('finance.fortnight-start'))->startOfDay();
        $day = isset($data['fortnight']) ? Carbon::parse($data['fortnight']) : now()->startOfDay();
        $fortnight = $anchor->copy()->addDays((int) floor($anchor->diffInDays($day) / 14) * 14);
        $query = DB::table('finance_time_entries')->where('user_id', $request->user()->id)->whereBetween('date', [$fortnight->toDateString(), $fortnight->copy()->addDays(13)->toDateString()]);
        $recorded = $query->orderBy('id')->get()->groupBy('date');
        $entries = collect(range(0, 13))->map(function ($offset) use ($fortnight, $recorded) {
            $date = $fortnight->copy()->addDays($offset);
            $records = $recorded->get($date->toDateString(), collect());

            return (object) ['date' => $date, 'minutes' => $records->sum('minutes'), 'notes' => $records->pluck('notes')->filter()->implode("\n"), 'records' => $records];
        });
        $hours = $entries->sum('minutes') / 60;
        $earned = (int) $recorded->flatten(1)->sum(fn ($record) => (int) round($record->minutes * $record->rate_cents / 60));

        return view('admin.timesheet.index', compact('tab', 'entries', 'fortnight', 'hours', 'earned'));
    }

    public function editor(Request $request): View
    {
        $data = $request->validate(['id' => 'nullable|integer', 'date' => 'nullable|date_format:Y-m-d|before_or_equal:today']);
        $entry = isset($data['id']) ? DB::table('finance_time_entries')->where('user_id', $request->user()->id)->where('id', $data['id'])->first() : null;
        abort_if(isset($data['id']) && ! $entry, 404);

        $date = $entry->date ?? $data['date'] ?? today()->toDateString();
        $dayEntries = DB::table('finance_time_entries')->where('user_id', $request->user()->id)->where('date', $date)->orderBy('id')->get();
        $entry ??= $dayEntries->first();

        return view('admin.timesheet.edit', compact('entry', 'date', 'dayEntries'));
    }

    public function store(Request $request, FinancePlanner $planner): RedirectResponse|JsonResponse
    {
        $daily = $request->routeIs('admin.timesheet.store');
        if ($daily) {
            $hours = $request->validate(['hours' => 'required|numeric|min:0|max:24'])['hours'];
            $request->merge(['minutes' => (int) round((float) $hours * 60), 'activity' => 'Business time', 'rate' => 60, 'workshop_id' => null]);
        }
        $data = $request->validate(['id' => 'nullable|integer', 'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'activity' => ['required', Rule::in(['Business time', 'Delivery', 'Preparation', 'Pack down', 'Travel', 'Administration', 'Development'])], 'minutes' => $daily ? 'required|integer|min:0|max:1440' : 'required|integer|min:1|max:1440', 'rate' => 'required|numeric|min:0|max:10000', 'workshop_id' => 'nullable|string|exists:workshops,id', 'notes' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($request, $data, $planner, $daily): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $query = DB::table('finance_time_entries')->where('user_id', $request->user()->id);
            if (! empty($data['id'])) {
                abort_unless((clone $query)->where('id', $data['id'])->exists(), 404);
            }
            $existing = ! empty($data['id']) ? (clone $query)->where('id', $data['id'])->first() : null;
            if ($daily && (! $existing || $existing->date !== $data['date']) && (clone $query)->where('date', $data['date'])->exists()) {
                throw ValidationException::withMessages(['date' => 'Time is already recorded for this date. Edit the existing entry to update your daily hours.']);
            }
            if ($daily) {
                $data['activity'] = $existing->activity ?? 'Business time';
                $data['workshop_id'] = $existing->workshop_id ?? null;
                $data['rate'] = ($existing->rate_cents ?? (clone $query)->orderByDesc('date')->orderByDesc('id')->value('rate_cents') ?? 6000) / 100;
            }
            $minutes = (int) (clone $query)->where('date', $data['date'])->when($data['id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))->sum('minutes');
            if ($minutes + $data['minutes'] > 1440) {
                throw ValidationException::withMessages(['minutes' => 'A day cannot contain more than 24 hours of time.']);
            }
            $values = ['user_id' => $request->user()->id, 'date' => $data['date'], 'activity' => $data['activity'], 'minutes' => $data['minutes'], 'rate_cents' => $planner->cents($data['rate']), 'workshop_id' => $data['workshop_id'] ?? null, 'notes' => $data['notes'] ?? null, 'updated_at' => now()];
            if (! empty($data['id'])) {
                (clone $query)->where('id', $data['id'])->update($values);
            } else {
                DB::table('finance_time_entries')->insert($values + ['created_at' => now()]);
            }
            if ($planner->earned($request->user()->id) < $planner->remunerationForgone($request->user()->id)) {
                throw ValidationException::withMessages(['rate' => 'This would reduce your earned remuneration below remuneration already forgone.']);
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Time recorded.']);
        }

        return redirect()->route('admin.timesheet.index', ['fortnight' => $data['date']])->with('message', 'Time recorded.')->with('message-type', 'success');
    }
}
