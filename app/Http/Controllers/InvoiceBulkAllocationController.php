<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\PricingVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceBulkAllocationController extends Controller
{
    public function preview(Request $request, FinancePlanner $planner): View
    {
        $data = $request->validate([
            'invoice_ids' => 'required|array|min:1|max:200',
            'invoice_ids.*' => 'required|integer|distinct|exists:invoices,id',
            'version_id' => 'nullable|integer|exists:finance_pricing_versions,id',
            'review' => 'nullable|boolean',
        ]);
        PricingVersion::assertSelectable(isset($data['version_id']) ? (int) $data['version_id'] : null);
        $version = PricingVersion::forDate(today()->toDateString(), $data['version_id'] ?? null);
        $preview = null;
        if ($request->boolean('review')) {
            $preview = $this->build($planner, $data['invoice_ids'], (int) $version->id);
            Cache::put($this->key($request, $preview['token']), ['input' => ['invoice_ids' => $data['invoice_ids'], 'version_id' => (int) $version->id], 'preview' => $preview], now()->addMinutes(30));
        }

        return view('admin.invoice.bulk-allocation', [
            'invoiceIds' => $data['invoice_ids'], 'version' => $version, 'preview' => $preview,
            'plans' => DB::table('finance_pricing_versions')->where('is_snapshot', false)->where('archived', false)->orWhere('id', $version->id)->orderBy('name')->get(),
            'categories' => DB::table('finance_categories')->orderBy('priority')->get(),
        ]);
    }

    public function apply(Request $request, FinancePlanner $planner): JsonResponse
    {
        $data = $request->validate(['token' => 'required|uuid', 'selected' => 'required|array|min:1|max:200', 'selected.*' => 'required|integer|distinct|min:0']);
        $cached = Cache::get($this->key($request, $data['token']));
        if (! $cached) {
            throw ValidationException::withMessages(['preview' => 'This preview has expired. Open the allocation editor and preview again.']);
        }
        $count = DB::transaction(function () use ($request, $planner, $data, $cached) {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $existing = DB::table('finance_batches')->where('token', $data['token'])->where('created_by', $request->user()->id)->first();
            if ($existing) {
                return DB::table('finance_budgets')->where('batch_id', $existing->id)->count();
            }
            $fresh = $this->build($planner, $cached['input']['invoice_ids'], $cached['input']['version_id']);
            foreach ($data['selected'] as $key) {
                $row = $cached['preview']['rows'][$key] ?? null;
                if (! $row || $row['warning'] || $row !== ($fresh['rows'][$key] ?? null)) {
                    throw ValidationException::withMessages(['preview' => 'A selected record has changed or cannot be allocated. Generate a new preview.']);
                }
            }
            $batch = $planner->apply($cached['preview'], $request->user()->id, $data['selected']);

            return DB::table('finance_budgets')->where('batch_id', $batch)->count();
        });

        return response()->json(['action' => 'invoice-allocation', 'message' => "Applied the plan to {$count} allocation groups. Ticket invoices share their workshop allocation."]);
    }

    private function build(FinancePlanner $planner, array $ids, int $version): array
    {
        $preview = $planner->preview(['invoice_ids' => $ids, 'version_id' => $version]);
        PricingVersion::assertSelectable($version);
        $plan = PricingVersion::forDate(today()->toDateString(), $version);
        foreach ($preview['rows'] as &$row) {
            if (! $row['workshop_id'] && Invoice::whereIn('id', $row['invoice_ids'])->where(fn ($q) => $q->where('status', Invoice::STATUS_CANCELLED)->orWhere('total_amount', '<', 0))->exists()) {
                $row['warning'] = 'Cancelled invoices and credit documents need individual review.';
            }
            $row['funding'] = $planner->funding($row['targets'], $row['income']['net'], $planner->rounding($plan, $row['assumptions']));
            $row['selected_invoice_count'] = count(array_intersect($ids, $row['invoice_ids']));
        }
        unset($row);

        return $preview;
    }

    private function key(Request $request, string $token): string
    {
        return 'invoice-allocation-preview:'.$request->user()->id.':'.$token;
    }
}
