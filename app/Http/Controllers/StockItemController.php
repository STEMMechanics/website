<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\StockItem;
use App\Models\StockItemGroup;
use App\Models\StockMovement;
use App\Models\StockReceiptLine;
use App\Models\StockReservation;
use App\Models\Workshop;
use App\Services\SiteListControls;
use App\Services\StockAttention;
use App\Services\StockInventoryService;
use App\Services\StoreInventoryAllocatorService;
use App\Support\ListPageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockItemController extends Controller
{
    public function index(Request $request, StockAttention $attention, StockInventoryService $inventory): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status_scope' => ['nullable', Rule::in(['all', 'active', 'archived'])],
        ]);
        $statusScope = $request->query('status_scope', 'active') ?: 'active';
        $statusCounts = $this->stockStatusCounts(false);

        $query = StockItem::query()
            ->where('is_kit', false)
            ->with(['image', 'group'])
            ->withCount(['products', 'variants', 'kitComponents', 'usedInKits', 'pickListItems'])
            ->orderBy('status')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('variant_name', 'like', '%'.$search.'%')
                    ->orWhereHas('group', fn ($groupQuery) => $groupQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($statusScope !== 'all') {
            $query->where('status', $statusScope);
        }

        $items = $query
            ->tap(fn ($listingQuery) => app(SiteListControls::class)->apply($listingQuery))
            ->paginate(ListPageSize::resolve(20))
            ->onEachSide(1);
        $reservedQuantities = $inventory->reservedQuantitiesForStockItems($items->getCollection());
        $items->getCollection()->each(fn (StockItem $item) => $item->setAttribute(
            'active_reserved_quantity',
            $reservedQuantities[(int) $item->id] ?? 0,
        ));

        return view('admin.shop.stock.index', [
            'stockItems' => $items,
            'attentionCount' => $attention->count(),
            'stockTabs' => $this->stockTabs('items'),
            'statusTabs' => $this->stockStatusTabs($statusScope, $statusCounts),
        ]);
    }

    public function kits(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status_scope' => ['nullable', Rule::in(['all', 'active', 'archived'])],
        ]);
        $statusScope = $request->query('status_scope', 'active') ?: 'active';
        $statusCounts = $this->stockStatusCounts(true);
        $search = trim((string) $request->query('search', ''));
        $query = StockItem::query()
            ->where('is_kit', true)
            ->with(['group'])
            ->withCount(['kitComponents', 'products', 'variants'])
            ->withSum(['reservations as active_reserved_quantity' => fn ($reservationQuery) => $reservationQuery->whereIn('status', StockReservation::ACTIVE_STATUSES)], 'remaining_quantity')
            ->orderBy('status')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('sku', 'like', '%'.$search.'%')
                ->orWhere('variant_name', 'like', '%'.$search.'%')
                ->orWhereHas('group', fn ($groupQuery) => $groupQuery->where('name', 'like', '%'.$search.'%')));
        }

        if ($statusScope !== 'all') {
            $query->where('status', $statusScope);
        }

        app(SiteListControls::class)->apply($query);
        $kits = $query
            ->paginate(ListPageSize::resolve(20), ['*'], 'kits_page')
            ->onEachSide(1);

        return view('admin.shop.stock.kits.index', [
            'kits' => $kits,
            'stockTabs' => $this->stockTabs('kits'),
            'statusTabs' => $this->stockStatusTabs($statusScope, $statusCounts),
        ]);
    }

    public function createKit(): View
    {
        return view('admin.shop.stock.kits.edit', [
            'kit' => new StockItem(['is_kit' => true, 'unit' => 'kit']),
            'stockItems' => StockItem::query()->with('group')->where('status', StockItem::STATUS_ACTIVE)->orderBy('name')->get(),
            'stockTabs' => $this->stockTabs('kits'),
        ]);
    }

    public function storeKit(Request $request): RedirectResponse
    {
        $validated = $this->validatedKit($request);
        $kit = DB::transaction(function () use ($validated): StockItem {
            $kit = new StockItem;
            $kit->fill($this->kitAttributes($validated));
            $kit->is_kit = true;
            $kit->on_hand_quantity = 0;
            $kit->save();
            $this->syncKitComponents($kit, $validated['components']);

            return $kit;
        });

        return redirect()->route('admin.shop.stock.kit.edit', $kit)->with([
            'message' => 'Kit created.',
            'message-title' => 'Kit created',
            'message-type' => 'success',
        ]);
    }

    public function duplicateKit(StockItem $stockItem): RedirectResponse
    {
        abort_unless($stockItem->is_kit, 404);
        $copy = DB::transaction(function () use ($stockItem): StockItem {
            $source = StockItem::query()->with('kitComponents')->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            $name = mb_substr('Copy of '.$source->linkLabel(), 0, 255);
            $copy = StockItem::query()->create([
                'name' => $name,
                'sku' => $this->generatedSku($name),
                'image_media_name' => $source->image_media_name,
                'unit' => $source->unit,
                'status' => $source->status,
                'is_kit' => true,
                'on_hand_quantity' => 0,
                'reorder_point' => 0,
                'stock_item_group_id' => null,
                'variant_name' => null,
                'replacement_unit_cost_ex_tax' => null,
                'replacement_cost_currency' => 'AUD',
                'replacement_cost_source' => null,
                'replacement_cost_updated_at' => null,
                'notes' => $source->notes,
            ]);

            foreach ($source->kitComponents as $component) {
                $copy->kitComponents()->create([
                    'component_stock_item_id' => $component->component_stock_item_id,
                    'quantity' => $component->quantity,
                    'note' => $component->note,
                    'sort_order' => $component->sort_order,
                ]);
            }

            return $copy;
        });

        return redirect()->route('admin.shop.stock.kit.edit', $copy)->with([
            'message' => 'Kit recipe copied. Rename the new kit and update its recipe as needed.',
            'message-title' => 'Kit duplicated',
            'message-type' => 'success',
        ]);
    }

    public function editKit(Request $request, StockItem $stockItem, StockInventoryService $inventory): View
    {
        abort_unless($stockItem->is_kit, 404);
        $stockItem->load(['image', 'kitComponents.component.group', 'products', 'variants.product', 'usedInKits.kit.group']);
        $stockItem->setAttribute('active_reserved_quantity', $stockItem->activeReservedQuantity());
        $activeStockReservations = $stockItem->activeReservations()
            ->where('remaining_quantity', '>', 0)
            ->orderBy('reserved_at')
            ->orderBy('id')
            ->get();
        $reservedWorkshopIds = $activeStockReservations
            ->where('source_type', Workshop::class)
            ->pluck('source_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();
        $reservedWorkshops = $reservedWorkshopIds->isNotEmpty()
            ? Workshop::query()->whereIn('id', $reservedWorkshopIds)->get()->keyBy(fn (Workshop $workshop): string => (string) $workshop->getKey())
            : collect();
        $activeStockReservations = $activeStockReservations->map(fn (StockReservation $reservation): array => [
            'reservation' => $reservation,
            'workshop' => $reservation->source_type === Workshop::class
                ? $reservedWorkshops->get((string) $reservation->source_id)
                : null,
        ])->sortBy(fn (array $reservationRow): int =>
            $reservationRow['workshop']?->starts_at?->getTimestamp() ?? PHP_INT_MAX
        )->values();
        $stockItem->loadCount('movements');
        $kitDeleteBlockers = $this->kitDeleteBlockers($stockItem);

        $errorBag = $request->session()->get('errors');
        $errorKeys = $errorBag instanceof \Illuminate\Support\ViewErrorBag
            ? $errorBag->getBag('default')->keys()
            : [];
        $hasAssemblyErrors = collect($errorKeys)->contains(fn (string $key): bool =>
            in_array($key, ['quantity', 'completed_quantity', 'assembly_notes', 'actual_usage'], true)
            || str_starts_with($key, 'actual_usage.')
        );
        $oldAssemblyQuantity = $request->session()->getOldInput('quantity');
        $assemblyPlan = null;
        if ($hasAssemblyErrors && filter_var($oldAssemblyQuantity, FILTER_VALIDATE_INT) !== false && (int) $oldAssemblyQuantity > 0 && (int) $oldAssemblyQuantity <= 100000) {
            try {
                $assemblyPlan = $inventory->assemblyPlan($stockItem, (int) $oldAssemblyQuantity);
            } catch (ValidationException) {
                // Keep the kit editor available if the saved recipe changed after the failed submission.
            }
        } elseif ($request->boolean('assemble')) {
            $requestedAssemblyQuantity = $request->query('quantity', 1);
            if (filter_var($requestedAssemblyQuantity, FILTER_VALIDATE_INT) !== false && (int) $requestedAssemblyQuantity > 0 && (int) $requestedAssemblyQuantity <= 100000) {
                try {
                    $assemblyPlan = $inventory->assemblyPlan($stockItem, (int) $requestedAssemblyQuantity);
                } catch (ValidationException) {
                    // Keep the kit editor available if its recipe is not ready to assemble.
                }
            }
        }

        return view('admin.shop.stock.kits.edit', [
            'kit' => $stockItem,
            'kitDeleteBlockers' => $kitDeleteBlockers,
            'kitArchiveBlockers' => $this->stockArchiveBlockers($stockItem),
            'assemblyPlan' => $assemblyPlan,
            'activeStockReservations' => $activeStockReservations,
            'stockItems' => StockItem::query()->with('group')->where('status', StockItem::STATUS_ACTIVE)->orderBy('name')->get(),
            'stockTabs' => $this->stockTabs('kits'),
        ]);
    }

    public function destroyKit(Request $request, StockItem $stockItem, StockInventoryService $inventory): RedirectResponse
    {
        abort_unless($stockItem->is_kit, 404);
        $validated = $request->validate([
            'disposition' => ['nullable', Rule::in(['write_off', 'return_components'])],
        ]);

        DB::transaction(function () use ($stockItem, $validated, $inventory, $request): void {
            $kit = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            abort_unless($kit->is_kit, 404);

            $blockers = $this->kitDeleteBlockers($kit);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['kit' => implode(' ', $blockers)]);
            }

            $onHand = max(0, (float) $kit->on_hand_quantity);
            $disposition = (string) ($validated['disposition'] ?? '');
            if ($onHand > 0 && ! in_array($disposition, ['write_off', 'return_components'], true)) {
                throw ValidationException::withMessages([
                    'disposition' => 'Choose whether to write off the ready-made stock or return its components to stock items.',
                ]);
            }

            if ($onHand > 0 && $disposition === 'return_components') {
                $components = $kit->kitComponents()->with('component')->get();
                if ($components->isEmpty()) {
                    throw ValidationException::withMessages([
                        'disposition' => 'This kit has no saved recipe to return. Choose to write off the ready-made stock instead.',
                    ]);
                }

                foreach ($components as $component) {
                    $stockItem = $component->component;
                    $quantity = round($onHand * (float) $component->quantity, 3);
                    if (! $stockItem instanceof StockItem || $quantity <= 0) {
                        continue;
                    }

                    $notes = 'Components returned when deleting '.$kit->name.'.';
                    if ($stockItem->is_kit) {
                        if (abs($quantity - round($quantity)) > 0.0005) {
                            throw ValidationException::withMessages([
                                'disposition' => 'The saved recipe returns a fractional quantity of '.$stockItem->name.'. Edit the recipe or write off the ready-made stock.',
                            ]);
                        }
                        $inventory->addExistingKits($stockItem, (int) round($quantity), $request->user(), $notes);
                    } else {
                        $inventory->adjust($stockItem, $quantity, null, $notes, $request->user());
                    }
                }
            } elseif ($onHand > 0 && $disposition === 'write_off') {
                $inventory->adjust(
                    $kit,
                    -$onHand,
                    null,
                    'Ready-made stock written off when deleting '.$kit->name.'.',
                    $request->user(),
                );
            }

            $kit->delete();
        }, 3);

        return redirect()->route('admin.shop.stock.kits')->with([
            'message' => 'Kit deleted.',
            'message-title' => 'Kit deleted',
            'message-type' => 'success',
        ]);
    }

    /** @return list<string> */
    private function kitDeleteBlockers(StockItem $kit): array
    {
        $kit->loadMissing(['products', 'variants.product', 'usedInKits.kit']);
        $blockers = [];

        if ($kit->usedInKits->isNotEmpty()) {
            $names = $kit->usedInKits->map(fn ($component): string => (string) (data_get($component, 'kit.name') ?? 'another kit'))->unique()->implode(', ');
            $blockers[] = 'Remove this kit from these kit recipes first: '.$names.'.';
        }
        if ($kit->products->isNotEmpty() || $kit->variants->isNotEmpty()) {
            $blockers[] = 'Unlink this kit from its store products and variants first.';
        }
        if (Schema::hasTable('product_stock_components')
            && DB::table('product_stock_components')->where('stock_item_id', $kit->id)->exists()) {
            $blockers[] = 'Remove this kit from product recipes first.';
        }
        if ($kit->pickListItems()->exists()) {
            $blockers[] = 'Remove this kit from workshop blueprints first.';
        }
        if ($kit->receiptLines()->exists()) {
            $blockers[] = 'This kit has receipt records. Remove those receipt lines before deleting it.';
        }
        if ($kit->activeReservations()->exists()) {
            $blockers[] = 'Release its active workshop or sales reservations first.';
        }

        return $blockers;
    }

    public function archive(StockItem $stockItem): RedirectResponse
    {
        $blockers = DB::transaction(function () use ($stockItem): array {
            $item = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            $blockers = $this->stockArchiveBlockers($item);

            if ($blockers === []) {
                $item->status = StockItem::STATUS_ARCHIVED;
                $item->save();
            }

            return $blockers;
        }, 3);

        if ($blockers !== []) {
            return redirect()->route($this->stockEditRoute($stockItem), $stockItem)
                ->withErrors(['status' => implode(' ', $blockers)]);
        }

        return redirect()->route($this->stockIndexRoute($stockItem))->with([
            'message' => 'Stock item archived. Its history is preserved.',
            'message-title' => 'Stock item archived',
            'message-type' => 'success',
        ]);
    }

    public function archiveItem(StockItem $stockItem): RedirectResponse
    {
        abort_if($stockItem->is_kit, 404);

        return $this->archive($stockItem);
    }

    public function archiveKit(StockItem $stockItem): RedirectResponse
    {
        abort_unless($stockItem->is_kit, 404);

        return $this->archive($stockItem);
    }

    public function restore(StockItem $stockItem): RedirectResponse
    {
        StockItem::query()->whereKey($stockItem->id)->update([
            'status' => StockItem::STATUS_ACTIVE,
            'updated_at' => now(),
        ]);

        return redirect()->route($this->stockEditRoute($stockItem), $stockItem)->with([
            'message' => 'Stock item restored.',
            'message-title' => 'Stock item restored',
            'message-type' => 'success',
        ]);
    }

    public function restoreItem(StockItem $stockItem): RedirectResponse
    {
        abort_if($stockItem->is_kit, 404);

        return $this->restore($stockItem);
    }

    public function restoreKit(StockItem $stockItem): RedirectResponse
    {
        abort_unless($stockItem->is_kit, 404);

        return $this->restore($stockItem);
    }

    public function destroy(StockItem $stockItem): RedirectResponse
    {
        abort_if($stockItem->is_kit, 404);

        DB::transaction(function () use ($stockItem): void {
            $item = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            abort_if($item->is_kit, 404);

            $blockers = $this->stockItemDeleteBlockers($item);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['delete' => implode(' ', $blockers)]);
            }

            $item->delete();
        }, 3);

        return redirect()->route('admin.shop.stock.index')->with([
            'message' => 'Unused stock item deleted.',
            'message-title' => 'Stock item deleted',
            'message-type' => 'success',
        ]);
    }

    /** @return list<string> */
    private function stockArchiveBlockers(StockItem $item): array
    {
        $item->loadMissing(['products', 'variants', 'usedInKits.kit']);
        $blockers = [];

        if ((float) $item->on_hand_quantity > 0) {
            $blockers[] = 'Use or adjust its on-hand stock to zero first.';
        }
        if ($item->activeReservations()->exists()) {
            $blockers[] = 'Release its active workshop or sales reservations first.';
        }
        if ($item->products->isNotEmpty() || $item->variants->isNotEmpty()) {
            $blockers[] = 'Unlink it from store products and variants first.';
        }
        if ($item->usedInKits->isNotEmpty()) {
            $names = $item->usedInKits->map(fn ($component): string => (string) (data_get($component, 'kit.name') ?? 'a kit'))->unique()->implode(', ');
            $blockers[] = 'Remove it from kit recipes first: '.$names.'.';
        }
        if ($item->pickListItems()->exists()) {
            $blockers[] = 'Remove it from workshop blueprints first.';
        }
        if (Schema::hasTable('product_stock_components')
            && DB::table('product_stock_components')->where('stock_item_id', $item->id)->exists()) {
            $blockers[] = 'Remove it from product recipes first.';
        }

        return $blockers;
    }

    /** @return list<string> */
    private function stockItemDeleteBlockers(StockItem $item): array
    {
        $blockers = $this->stockArchiveBlockers($item);
        if ($item->receiptLines()->exists()) {
            $blockers[] = 'Receipt history exists. Archive the item to preserve its purchasing records.';
        }
        if ($item->movements()->exists()) {
            $blockers[] = 'Stock movement history exists. Archive the item to preserve its ledger.';
        }
        if ($item->reservations()->exists()) {
            $blockers[] = 'Reservation history exists. Archive the item to preserve its workshop and sales records.';
        }

        return $blockers;
    }

    private function stockEditRoute(StockItem $item): string
    {
        return $item->is_kit ? 'admin.shop.stock.kit.edit' : 'admin.shop.stock.edit';
    }

    private function stockIndexRoute(StockItem $item): string
    {
        return $item->is_kit ? 'admin.shop.stock.kits' : 'admin.shop.stock.index';
    }

    private function workshopAssemblyDialogReturn(Request $request, StockItem $kit): ?RedirectResponse
    {
        $assemblyWorkshopId = $request->input('workshop_id');
        $workshopId = $assemblyWorkshopId ?: $request->input('workshop_return_id');
        $quantity = filter_var($request->input('quantity'), FILTER_VALIDATE_INT);
        if (! is_string($workshopId) || $workshopId === '' || $quantity === false || $quantity < 1 || $quantity > 100000) {
            return null;
        }

        $workshop = Workshop::query()->find($workshopId);
        if (! $workshop instanceof Workshop) {
            return null;
        }

        $returnTo = in_array($request->input('workshop_return_to'), ['run-sheet', 'stock-reconciliation'], true)
            ? $request->input('workshop_return_to')
            : 'stock-reconciliation';
        $returnRoute = $returnTo === 'run-sheet'
            ? 'admin.workshop.run-sheet'
            : 'admin.workshop.stock-reconciliation';
        $previewParameters = [
            'stockItem' => $kit,
            'quantity' => $quantity,
            'workshop_return_to' => $returnTo,
        ];
        $previewParameters[$assemblyWorkshopId ? 'workshop_id' : 'workshop_return_id'] = (string) $workshop->getKey();
        $previewUrl = route('admin.shop.stock.kit.assemble.preview', $previewParameters);

        return redirect()->route($returnRoute, [
            'workshop' => $workshop,
            'assembly_preview' => $previewUrl,
        ]);
    }

    public function kitInventory(StockItem $stockItem): RedirectResponse
    {
        abort_unless($stockItem->is_kit, 404);

        return redirect()->route('admin.shop.stock.kit.edit', $stockItem);
    }

    public function assemblyPreview(Request $request, StockItem $stockItem, StockInventoryService $inventory): View|RedirectResponse
    {
        abort_unless($stockItem->is_kit, 404);
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'workshop_id' => ['nullable', 'string', Rule::exists('workshops', 'id')],
            'workshop_return_id' => ['nullable', 'string', Rule::exists('workshops', 'id')],
            'workshop_return_to' => ['nullable', Rule::in(['run-sheet', 'stock-reconciliation'])],
        ]);
        $workshop = isset($validated['workshop_id'])
            ? Workshop::query()->findOrFail($validated['workshop_id'])
            : null;
        $plan = $inventory->assemblyPlan($stockItem, (int) $validated['quantity'], $workshop);

        $viewData = [
            'kit' => $plan['kit'],
            'plannedQuantity' => $plan['quantity'],
            'completedQuantity' => old('completed_quantity', (string) $plan['quantity']),
            'assemblyRows' => $plan['rows'],
            'stockTabs' => $this->stockTabs('kits'),
            'workshop' => $workshop,
            'workshopReturnId' => $validated['workshop_return_id'] ?? ($workshop instanceof Workshop ? (string) $workshop->getKey() : null),
            'workshopReturnTo' => $validated['workshop_return_to'] ?? 'stock-reconciliation',
        ];

        if ($request->expectsJson()) {
            return view('admin.shop.stock.kits.assembly-dialog-content', $viewData + ['dialogMode' => true]);
        }

        $returnWorkshop = $workshop instanceof Workshop
            ? $workshop
            : (isset($validated['workshop_return_id']) ? Workshop::query()->findOrFail($validated['workshop_return_id']) : null);
        if ($returnWorkshop instanceof Workshop) {
            $returnRoute = $viewData['workshopReturnTo'] === 'run-sheet'
                ? 'admin.workshop.run-sheet'
                : 'admin.workshop.stock-reconciliation';
            $previewParameters = [
                'stockItem' => $stockItem,
                'quantity' => $plan['quantity'],
                'workshop_return_to' => $viewData['workshopReturnTo'],
            ];
            $previewParameters[$workshop instanceof Workshop ? 'workshop_id' : 'workshop_return_id'] = (string) $returnWorkshop->getKey();
            $previewUrl = route('admin.shop.stock.kit.assemble.preview', $previewParameters);

            return redirect()->route($returnRoute, [
                'workshop' => $returnWorkshop,
                'assembly_preview' => $previewUrl,
            ]);
        }

        return redirect()->route('admin.shop.stock.kit.edit', [
            'stockItem' => $stockItem,
            'assemble' => 1,
            'quantity' => $plan['quantity'],
        ]);
    }

    public function updateKit(Request $request, StockItem $stockItem, StockInventoryService $inventory): RedirectResponse
    {
        abort_unless($stockItem->is_kit, 404);
        $validated = $this->validatedKit($request, $stockItem);
        DB::transaction(function () use ($stockItem, $validated, $inventory): void {
            $lockedKit = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            if ($lockedKit->status !== StockItem::STATUS_ARCHIVED
                && $validated['status'] === StockItem::STATUS_ARCHIVED) {
                $blockers = $this->stockArchiveBlockers($lockedKit);
                if ($blockers !== []) {
                    throw ValidationException::withMessages(['status' => implode(' ', $blockers)]);
                }
            }
            $lockedKit->fill($this->kitAttributes($validated, $lockedKit));
            $lockedKit->is_kit = true;
            $lockedKit->save();
            $this->syncKitComponents($lockedKit, $validated['components']);
            $inventory->syncWorkshopReservationsUsingStockItem($lockedKit);
        });

        return redirect()->route('admin.shop.stock.kit.edit', $stockItem)->with([
            'message' => 'Kit updated.',
            'message-title' => 'Kit updated',
            'message-type' => 'success',
        ]);
    }

    public function assemble(Request $request, StockItem $stockItem, StockInventoryService $inventory, StoreInventoryAllocatorService $allocator): RedirectResponse
    {
        abort_unless($stockItem->is_kit, 404);
        try {
            $validated = $request->validate([
                'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
                'completed_quantity' => ['required', 'integer', 'min:0', 'max:100000'],
                'actual_usage' => ['required', 'array', 'min:1'],
                'actual_usage.*' => ['required', 'numeric', 'min:0', 'max:1000000'],
                'assembly_notes' => ['nullable', 'string', 'max:2000'],
                'workshop_id' => ['nullable', 'string', Rule::exists('workshops', 'id')],
                'workshop_return_id' => ['nullable', 'string', Rule::exists('workshops', 'id')],
                'workshop_return_to' => ['nullable', Rule::in(['run-sheet', 'stock-reconciliation'])],
            ]);
            if ((int) $validated['completed_quantity'] > (int) $validated['quantity']) {
                throw ValidationException::withMessages([
                    'completed_quantity' => 'The completed quantity cannot exceed the planned quantity.',
                ]);
            }
        } catch (ValidationException $exception) {
            $redirect = $this->workshopAssemblyDialogReturn($request, $stockItem);
            if ($redirect instanceof RedirectResponse) {
                return $redirect->withErrors($exception->errors())->withInput();
            }

            throw $exception;
        }

        $workshop = isset($validated['workshop_id'])
            ? Workshop::query()->findOrFail($validated['workshop_id'])
            : null;
        try {
            $inventory->assemble(
                $stockItem,
                (int) $validated['quantity'],
                (int) $validated['completed_quantity'],
                $validated['actual_usage'],
                $request->user(),
                $validated['assembly_notes'] ?? null,
                $workshop,
            );
        } catch (ValidationException $exception) {
            $redirect = $this->workshopAssemblyDialogReturn($request, $stockItem);
            if ($redirect instanceof RedirectResponse) {
                return $redirect->withErrors($exception->errors())->withInput();
            }

            throw $exception;
        }
        $allocator->allocateForStockItem($stockItem);

        $completedQuantity = (int) $validated['completed_quantity'];
        $message = $completedQuantity > 0
            ? $completedQuantity.' finished '.Str::plural('kit', $completedQuantity).' added to stock. Material use reconciled.'
            : 'Material use recorded. No finished kits were added to stock.';

        $returnWorkshop = $workshop instanceof Workshop
            ? $workshop
            : (isset($validated['workshop_return_id']) ? Workshop::query()->findOrFail($validated['workshop_return_id']) : null);
        if ($returnWorkshop instanceof Workshop) {
            $returnRoute = ($validated['workshop_return_to'] ?? 'stock-reconciliation') === 'run-sheet'
                ? 'admin.workshop.run-sheet'
                : 'admin.workshop.stock-reconciliation';

            return redirect()->route($returnRoute, $returnWorkshop)->with([
                'message' => $message,
                'message-title' => 'Assembly recorded',
                'message-type' => 'success',
            ]);
        }

        return redirect()->route('admin.shop.stock.kit.edit', $stockItem)->with([
            'message' => $message,
            'message-title' => 'Assembly recorded',
            'message-type' => 'success',
        ]);
    }

    public function adjustReadyMade(Request $request, StockItem $stockItem, StockInventoryService $inventory, StoreInventoryAllocatorService $allocator): RedirectResponse
    {
        abort_unless($stockItem->is_kit, 404);
        $validated = $request->validate([
            'adjustment_quantity' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $quantity = (int) $validated['adjustment_quantity'];

        if ($quantity > 0) {
            $inventory->addExistingKits($stockItem, $quantity, $request->user(), $validated['notes'] ?? null);
        } else {
            try {
                $inventory->adjust($stockItem, (float) $quantity, null, $validated['notes'] ?? null, $request->user());
            } catch (ValidationException $exception) {
                $errors = $exception->errors();
                if (isset($errors['quantity'])) {
                    throw ValidationException::withMessages(['adjustment_quantity' => $errors['quantity'][0]]);
                }

                throw $exception;
            }
        }
        $allocator->allocateForStockItem($stockItem);

        return $this->stockAdjustmentRedirect($stockItem, $inventory, 'Ready-made stock adjusted.');
    }

    public function create(): View
    {
        return view('admin.shop.stock.edit', [
            'stockItem' => new StockItem(['status' => StockItem::STATUS_ACTIVE, 'unit' => 'each']),
            'receiptExpenses' => $this->receiptExpenseOptions(),
            'stockTabs' => $this->stockTabs('items'),
        ]);
    }

    public function quickStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:120'],
            'sku_auto_generated' => ['nullable', 'boolean'],
            'unit' => ['required', 'string', 'max:32'],
        ]);

        $name = trim((string) $validated['name']);
        $sku = trim((string) ($validated['sku'] ?? ''));
        $autoGenerateSku = $sku === '' || (bool) ($validated['sku_auto_generated'] ?? false);
        if (! $autoGenerateSku && StockItem::query()->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->exists()) {
            throw ValidationException::withMessages(['sku' => 'That SKU is already in use.']);
        }
        if ($autoGenerateSku) {
            $sku = $this->generatedSku($name);
        }

        $stockItem = DB::transaction(function () use ($name, $sku, $validated): StockItem {
            return StockItem::query()->create([
                'name' => $name,
                'sku' => $sku,
                'unit' => trim((string) $validated['unit']) ?: 'each',
                'status' => StockItem::STATUS_ACTIVE,
                'is_kit' => false,
                'on_hand_quantity' => 0,
                'reorder_point' => 0,
                'replacement_unit_cost_ex_tax' => null,
                'replacement_cost_currency' => 'AUD',
                'replacement_cost_source' => null,
                'replacement_cost_updated_at' => null,
            ]);
        });

        return response()->json([
            'item' => [
                'id' => (int) $stockItem->id,
                'name' => $stockItem->linkLabel(),
                'sku' => (string) ($stockItem->sku ?? ''),
                'unit' => (string) $stockItem->unit,
                'status' => (string) $stockItem->status,
                'is_kit' => (bool) $stockItem->is_kit,
            ],
        ], 201);
    }

    public function store(Request $request, StockInventoryService $inventory): RedirectResponse
    {
        $validated = $this->validated($request);
        $openingQuantity = (float) ($validated['opening_quantity'] ?? 0);
        if ($validated['status'] === StockItem::STATUS_ARCHIVED && $openingQuantity > 0) {
            throw ValidationException::withMessages(['status' => 'Create the item as active while it has opening stock. You can archive it after stock and links are cleared.']);
        }
        $openingCost = ($validated['opening_unit_cost_ex_tax'] ?? $validated['replacement_unit_cost_ex_tax'] ?? null) !== null
            ? (float) ($validated['opening_unit_cost_ex_tax'] ?? $validated['replacement_unit_cost_ex_tax'])
            : null;

        $stockItem = DB::transaction(function () use ($validated, $openingQuantity, $openingCost, $inventory, $request): StockItem {
            $stockItem = new StockItem;
            $stockItem->fill($this->stockAttributes($validated));
            $stockItem->on_hand_quantity = 0;
            $stockItem->save();
            if ($openingQuantity > 0) {
                $inventory->adjust($stockItem, $openingQuantity, $openingCost, 'Opening stock', $request->user());
            }

            return $stockItem;
        });

        return redirect()->route('admin.shop.stock.edit', $stockItem)->with([
            'message' => 'Stock item created.',
            'message-title' => 'Stock item created',
            'message-type' => 'success',
        ]);
    }

    public function edit(StockItem $stockItem): View|RedirectResponse
    {
        if ($stockItem->is_kit) {
            return redirect()->route('admin.shop.stock.kit.edit', $stockItem);
        }
        $stockItem->load([
            'image', 'group',
            'products',
            'variants.product',
            'usedInKits.kit.group',
            'usedInKits.kit.products',
            'usedInKits.kit.variants.product',
            'pickListItems.template',
            'pickListItems.stockItem.group',
            'receiptLines.receipt',
            'movements.receiptLine.receipt.supplier',
            'movements.receiptLine.receipt.expense',
        ]);

        $stockItem->setAttribute('active_reserved_quantity', $stockItem->activeReservedQuantity());

        $activeStockReservations = $stockItem->activeReservations()
            ->where('remaining_quantity', '>', 0)
            ->orderBy('reserved_at')
            ->orderBy('id')
            ->get();
        $reservedWorkshopIds = $activeStockReservations
            ->where('source_type', Workshop::class)
            ->pluck('source_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();
        $reservedWorkshops = $reservedWorkshopIds->isNotEmpty()
            ? Workshop::query()->whereIn('id', $reservedWorkshopIds)->get()->keyBy(fn (Workshop $workshop): string => (string) $workshop->getKey())
            : collect();
        $activeStockReservations = $activeStockReservations->map(fn (StockReservation $reservation): array => [
            'reservation' => $reservation,
            'workshop' => $reservation->source_type === Workshop::class
                ? $reservedWorkshops->get((string) $reservation->source_id)
                : null,
        ])->sortBy(fn (array $reservationRow): int =>
            $reservationRow['workshop']?->starts_at?->getTimestamp() ?? PHP_INT_MAX
        )->values();

        return view('admin.shop.stock.edit', [
            'stockItem' => $stockItem,
            'activeStockReservations' => $activeStockReservations,
            'receiptExpenses' => $this->receiptExpenseOptions(),
            'stockItemDeleteBlockers' => $this->stockItemDeleteBlockers($stockItem),
            'stockTabs' => $this->stockTabs('items'),
        ]);
    }

    public function update(Request $request, StockItem $stockItem, StockInventoryService $inventory): RedirectResponse
    {
        abort_unless(! $stockItem->is_kit, 404);
        DB::transaction(function () use ($request, $stockItem, $inventory): void {
            $lockedItem = StockItem::query()->whereKey($stockItem->id)->lockForUpdate()->firstOrFail();
            $validated = $this->validated($request, $lockedItem);
            if ($lockedItem->status !== StockItem::STATUS_ARCHIVED
                && $validated['status'] === StockItem::STATUS_ARCHIVED) {
                $blockers = $this->stockArchiveBlockers($lockedItem);
                if ($blockers !== []) {
                    throw ValidationException::withMessages(['status' => implode(' ', $blockers)]);
                }
            }

            $lockedItem->fill($this->stockAttributes($validated, $lockedItem));
            $lockedItem->save();
            $inventory->syncWorkshopReservationsUsingStockItem($lockedItem);
        });

        return redirect()->route('admin.shop.stock.edit', $stockItem)->with([
            'message' => 'Stock item updated.',
            'message-title' => 'Stock item updated',
            'message-type' => 'success',
        ]);
    }

    public function receive(Request $request, StockItem $stockItem, StockInventoryService $inventory, StoreInventoryAllocatorService $allocator): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'total_cost_ex_tax' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'expense_id' => ['nullable', 'integer', 'exists:expenses,id'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $expense = ! empty($validated['expense_id']) ? Expense::query()->findOrFail((int) $validated['expense_id']) : null;
        $totalCostExTax = $this->receiptTotalCost($validated['total_cost_ex_tax'] ?? null, $expense);
        $inventory->receive($stockItem, (float) $validated['quantity'], $totalCostExTax, [
            'supplier' => $validated['supplier'] ?? '',
            'expense_id' => $validated['expense_id'] ?? null,
            'received_at' => $validated['date'],
            'notes' => $validated['notes'] ?? '',
        ], $request->user());
        $allocator->allocateForStockItem($stockItem->fresh());

        return redirect()->route('admin.shop.stock.edit', $stockItem)->with([
            'message' => 'Stock received and linked backorders reviewed.',
            'message-title' => 'Stock received',
            'message-type' => 'success',
        ]);
    }

    public function updateReceipt(
        Request $request,
        StockItem $stockItem,
        StockReceiptLine $receiptLine,
        StockInventoryService $inventory,
        StoreInventoryAllocatorService $allocator,
    ): RedirectResponse {
        abort_unless(! $stockItem->is_kit && (int) $receiptLine->stock_item_id === (int) $stockItem->id, 404);

        $prefix = 'receipt_'.$receiptLine->id.'_';
        $validated = $request->validate([
            'stock_receipt_line_id' => ['required', 'integer', Rule::in([(int) $receiptLine->id])],
            $prefix.'quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            $prefix.'total_cost_ex_tax' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            $prefix.'supplier' => ['nullable', 'string', 'max:255'],
            $prefix.'expense_id' => ['nullable', 'integer', 'exists:expenses,id'],
            $prefix.'received_at' => ['required', 'date'],
            $prefix.'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $expense = ! empty($validated[$prefix.'expense_id'])
            ? Expense::query()->findOrFail((int) $validated[$prefix.'expense_id'])
            : null;
        $totalCostExTax = $this->receiptTotalCost($validated[$prefix.'total_cost_ex_tax'] ?? null, $expense, $prefix.'total_cost_ex_tax');

        $inventory->updateReceiptLine(
            $stockItem,
            $receiptLine,
            (float) $validated[$prefix.'quantity'],
            $totalCostExTax,
            [
                'supplier' => $validated[$prefix.'supplier'] ?? '',
                'expense_id' => $validated[$prefix.'expense_id'] ?? null,
                'received_at' => $validated[$prefix.'received_at'],
                'notes' => $validated[$prefix.'notes'] ?? '',
            ],
        );
        $allocator->allocateForStockItem($stockItem->fresh());

        return redirect()->route('admin.shop.stock.edit', $stockItem)->with([
            'message' => 'Stock receipt updated.',
            'message-title' => 'Receipt updated',
            'message-type' => 'success',
        ]);
    }

    public function deleteReceipt(
        StockItem $stockItem,
        StockReceiptLine $receiptLine,
        StockInventoryService $inventory,
        StoreInventoryAllocatorService $allocator,
    ): RedirectResponse {
        abort_unless(! $stockItem->is_kit && (int) $receiptLine->stock_item_id === (int) $stockItem->id, 404);
        $inventory->deleteReceiptLine($stockItem, $receiptLine);
        $allocator->allocateForStockItem($stockItem->fresh());

        return redirect()->route('admin.shop.stock.edit', $stockItem)->with([
            'message' => 'Stock receipt deleted and inventory updated.',
            'message-title' => 'Receipt deleted',
            'message-type' => 'success',
        ]);
    }

    public function adjust(Request $request, StockItem $stockItem, StockInventoryService $inventory, StoreInventoryAllocatorService $allocator): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $inventory->adjust($stockItem, (float) $validated['quantity'], null, $validated['notes'] ?? null, $request->user(), $validated['date']);
        if ((float) $validated['quantity'] > 0) {
            $allocator->allocateForStockItem($stockItem->fresh());
        }

        return $this->stockAdjustmentRedirect($stockItem, $inventory, 'Stock adjusted.');
    }

    public function updateAdjustment(
        Request $request,
        StockItem $stockItem,
        StockMovement $movement,
        StockInventoryService $inventory,
        StoreInventoryAllocatorService $allocator,
    ): RedirectResponse {
        abort_unless(
            ! $stockItem->is_kit
                && (int) $movement->stock_item_id === (int) $stockItem->id
                && $movement->movement_type === StockMovement::TYPE_ADJUSTMENT
                && $movement->stock_receipt_line_id === null
                && $movement->source_type === null
                && $movement->source_id === null,
            404,
        );

        $prefix = 'adjustment_'.$movement->id.'_';
        $validated = $request->validate([
            'stock_adjustment_movement_id' => ['required', 'integer', Rule::in([(int) $movement->id])],
            $prefix.'quantity' => ['required', 'numeric', 'decimal:0,3', 'between:-100000,100000', 'not_in:0'],
            $prefix.'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $inventory->updateAdjustment(
                $stockItem,
                $movement,
                (float) $validated[$prefix.'quantity'],
                $validated[$prefix.'notes'] ?? null,
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (isset($errors['quantity'])) {
                throw ValidationException::withMessages([$prefix.'quantity' => $errors['quantity'][0]]);
            }
            if (isset($errors['adjustment'])) {
                throw ValidationException::withMessages(['stock_adjustment' => $errors['adjustment'][0]]);
            }

            throw $exception;
        }
        $allocator->allocateForStockItem($stockItem->fresh());

        return $this->stockAdjustmentRedirect($stockItem, $inventory, 'Stock adjustment updated.');
    }

    private function stockAdjustmentRedirect(
        StockItem $stockItem,
        StockInventoryService $inventory,
        string $successMessage,
    ): RedirectResponse {
        $shortage = $inventory->reservationShortages([(int) $stockItem->id])->first();
        if ($shortage instanceof StockItem) {
            return redirect()->route($stockItem->is_kit ? 'admin.shop.stock.kit.edit' : 'admin.shop.stock.edit', $stockItem)->with([
                'message' => 'Stock was adjusted, but active reservations are short by '
                    .$shortage->formatQuantity((float) ($shortage->reservation_shortage ?? 0)).' '.$shortage->unit
                    .' for '.$shortage->linkLabel().'. Review the reservations below or replenish stock.',
                'message-title' => 'Reservation stock warning',
                'message-type' => 'warning',
            ]);
        }

        return redirect()->route($stockItem->is_kit ? 'admin.shop.stock.kit.edit' : 'admin.shop.stock.edit', $stockItem)->with([
            'message' => $successMessage,
            'message-title' => match ($successMessage) {
                'Stock adjusted.' => 'Stock adjusted',
                'Ready-made stock adjusted.' => 'Stock adjusted',
                default => 'Adjustment updated',
            },
            'message-type' => 'success',
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?StockItem $stockItem = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => array_merge(
                ['nullable', 'string', 'max:120'],
                $request->boolean('sku_auto_generated')
                    ? []
                    : [Rule::unique('stock_items', 'sku')->ignore($stockItem?->id)],
            ),
            'sku_auto_generated' => ['nullable', 'boolean'],
            'image_media_name' => ['nullable', 'string', 'max:255', Rule::exists('media', 'name')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%')->where('visibility', 'public'))],
            'unit' => ['required', 'string', 'max:32'],
            'status' => ['required', Rule::in([StockItem::STATUS_ACTIVE, StockItem::STATUS_ARCHIVED])],
            'shared_workshop_supply' => ['sometimes', 'boolean'],
            'stock_item_group_id' => ['nullable', 'integer', Rule::exists('stock_item_groups', 'id')->where('kind', StockItemGroup::KIND_ITEMS)],
            'variant_name' => ['nullable', 'string', 'max:100'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'replacement_unit_cost_ex_tax' => ['nullable', 'numeric', 'min:0'],
            'replacement_cost_reset' => ['nullable', 'boolean'],
            'replacement_cost_currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'opening_quantity' => ['nullable', 'numeric', 'min:0'],
            'opening_unit_cost_ex_tax' => ['nullable', 'numeric', 'min:0'],
        ]);

        return array_merge($validated, $this->validatedVariantFields($validated, $stockItem));
    }

    /** @param array<string, mixed> $validated */
    private function stockAttributes(array $validated, ?StockItem $stockItem = null): array
    {
        $resetReplacementCost = (bool) ($validated['replacement_cost_reset'] ?? false);
        $replacementCost = $validated['replacement_unit_cost_ex_tax'] ?? null;
        $hasManualCost = ! $resetReplacementCost && $replacementCost !== null;
        $keepLatestReceiptCost = ! $hasManualCost
            && ! $resetReplacementCost
            && $stockItem?->replacement_cost_source === 'latest_receipt';
        $latestReceiptLine = $resetReplacementCost && $stockItem
            ? StockReceiptLine::query()->where('stock_item_id', $stockItem->id)->orderByDesc('id')->first()
            : null;
        $sku = trim((string) ($validated['sku'] ?? ''));

        if ($sku === '' || (bool) ($validated['sku_auto_generated'] ?? false)) {
            $sku = $this->generatedSku((string) $validated['name'], $stockItem);
        }

        return [
            'name' => trim((string) $validated['name']),
            'sku' => $sku,
            'image_media_name' => trim((string) ($validated['image_media_name'] ?? '')) ?: null,
            'unit' => trim((string) $validated['unit']) ?: 'each',
            'status' => (string) $validated['status'],
            'shared_workshop_supply' => (bool) ($validated['shared_workshop_supply'] ?? false),
            'stock_item_group_id' => $validated['stock_item_group_id'] ?? null,
            'variant_name' => trim((string) ($validated['variant_name'] ?? '')) ?: null,
            'reorder_point' => round((float) ($validated['reorder_point'] ?? 0), 3),
            'replacement_unit_cost_ex_tax' => $resetReplacementCost
                ? $latestReceiptLine?->unit_cost_ex_tax
                : ($hasManualCost
                    ? round((float) $replacementCost, 4)
                    : ($keepLatestReceiptCost ? $stockItem->replacement_unit_cost_ex_tax : null)),
            'replacement_cost_currency' => $resetReplacementCost
                ? 'AUD'
                : ($hasManualCost
                    ? strtoupper(trim((string) ($validated['replacement_cost_currency'] ?? 'AUD'))) ?: 'AUD'
                    : ($keepLatestReceiptCost ? $stockItem->replacement_cost_currency : 'AUD')),
            'replacement_cost_source' => $resetReplacementCost
                ? ($latestReceiptLine ? 'latest_receipt' : null)
                : ($hasManualCost
                    ? 'manual'
                    : ($keepLatestReceiptCost ? $stockItem->replacement_cost_source : null)),
            'replacement_cost_updated_at' => $resetReplacementCost
                ? ($latestReceiptLine ? now() : null)
                : ($hasManualCost
                    ? now()
                    : ($keepLatestReceiptCost ? $stockItem->replacement_cost_updated_at : null)),
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ];
    }

    /** @return array<int, array{title: string, route: string, active: bool}> */
    private function stockTabs(string $active): array
    {
        return [
            ['title' => 'Items', 'route' => route('admin.shop.stock.index'), 'active' => $active === 'items'],
            ['title' => 'Kits', 'route' => route('admin.shop.stock.kits'), 'active' => $active === 'kits'],
        ];
    }

    /** @return array{all: int, active: int, archived: int} */
    private function stockStatusCounts(bool $kits): array
    {
        $query = StockItem::query()->where('is_kit', $kits);

        return [
            'all' => (clone $query)->count(),
            'active' => (clone $query)->where('status', StockItem::STATUS_ACTIVE)->count(),
            'archived' => (clone $query)->where('status', StockItem::STATUS_ARCHIVED)->count(),
        ];
    }

    /** @param array{all: int, active: int, archived: int} $counts
     *  @return list<array{title: string, count: int, active: bool, route: string}>
     */
    private function stockStatusTabs(string $active, array $counts): array
    {
        return collect(['all' => 'All', 'active' => 'Active', 'archived' => 'Archived'])
            ->map(fn (string $title, string $key): array => [
                'title' => $title,
                'count' => $counts[$key],
                'active' => $active === $key,
                'route' => request()->fullUrlWithQuery(['status_scope' => $key, 'page' => null, 'kits_page' => null]),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function validatedKit(Request $request, ?StockItem $kit = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => array_merge(
                ['nullable', 'string', 'max:120'],
                $request->boolean('sku_auto_generated')
                    ? []
                    : [Rule::unique('stock_items', 'sku')->ignore($kit?->id)],
            ),
            'sku_auto_generated' => ['nullable', 'boolean'],
            'image_media_name' => ['nullable', 'string', 'max:255', Rule::exists('media', 'name')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%')->where('visibility', 'public'))],
            'unit' => ['required', 'string', 'max:32'],
            'status' => ['required', Rule::in([StockItem::STATUS_ACTIVE, StockItem::STATUS_ARCHIVED])],
            'notes' => ['nullable', 'string'],
            'components' => ['required', 'array', 'min:1', 'max:100'],
            'components.*.stock_item_id' => ['required', 'integer', Rule::exists('stock_items', 'id')->where('status', StockItem::STATUS_ACTIVE)],
            'components.*.quantity' => ['required', 'numeric', 'gt:0'],
            'components.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'components.*.stock_item_id.required' => 'Choose a stock item for each kit row.',
            'components.*.quantity.required' => 'Enter a quantity for each kit row.',
            'components.*.quantity.gt' => 'Each quantity must be greater than zero.',
        ]);
        $validated = array_merge($validated, $this->validatedVariantFields($validated, $kit));

        $componentIds = collect($validated['components'])
            ->map(fn (array $component): int => (int) $component['stock_item_id'])
            ->values();
        if ($componentIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['components' => 'Each stock item can only be added once to a kit.']);
        }
        if ($kit && $componentIds->contains((int) $kit->id)) {
            throw ValidationException::withMessages(['components' => 'A kit cannot contain itself.']);
        }
        if ($kit) {
            foreach ($componentIds as $componentId) {
                if ($this->kitContainsStockItem($componentId, (int) $kit->id, [])) {
                    throw ValidationException::withMessages(['components' => 'This component would create a circular kit recipe.']);
                }
            }
        }

        $validated['components'] = collect($validated['components'])
            ->values()
            ->map(fn (array $component, int $index): array => [
                'stock_item_id' => (int) $component['stock_item_id'],
                'quantity' => round((float) $component['quantity'], 3),
                'note' => trim((string) ($component['note'] ?? '')) ?: null,
                'sort_order' => ($index + 1) * 10,
            ])
            ->all();

        return $validated;
    }

    private function kitContainsStockItem(int $stockItemId, int $needleId, array $visited): bool
    {
        if ($stockItemId === $needleId) {
            return true;
        }
        if (isset($visited[$stockItemId])) {
            return false;
        }
        $visited[$stockItemId] = true;

        return DB::table('stock_item_components')
            ->where('kit_stock_item_id', $stockItemId)
            ->pluck('component_stock_item_id')
            ->contains(fn ($childId): bool => $this->kitContainsStockItem((int) $childId, $needleId, $visited));
    }

    /** @param array<string, mixed> $validated */
    private function kitAttributes(array $validated, ?StockItem $kit = null): array
    {
        $sku = trim((string) ($validated['sku'] ?? ''));
        if ($sku === '' || (bool) ($validated['sku_auto_generated'] ?? false)) {
            $sku = $this->generatedSku((string) $validated['name'], $kit);
        }

        return [
            'name' => trim((string) $validated['name']),
            'sku' => $sku,
            'image_media_name' => trim((string) ($validated['image_media_name'] ?? '')) ?: null,
            'unit' => trim((string) ($validated['unit'] ?? 'kit')) ?: 'kit',
            'status' => (string) $validated['status'],
            'stock_item_group_id' => $validated['stock_item_group_id'] ?? null,
            'variant_name' => trim((string) ($validated['variant_name'] ?? '')) ?: null,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
            'replacement_unit_cost_ex_tax' => null,
            'replacement_cost_currency' => 'AUD',
            'replacement_cost_source' => null,
            'replacement_cost_updated_at' => null,
        ];
    }

    /** @param array<int, array{stock_item_id: int, quantity: float, note: ?string, sort_order: int}> $components */
    private function syncKitComponents(StockItem $kit, array $components): void
    {
        $kit->kitComponents()->delete();
        foreach ($components as $component) {
            $kit->kitComponents()->create([
                'component_stock_item_id' => $component['stock_item_id'],
                'quantity' => $component['quantity'],
                'note' => $component['note'],
                'sort_order' => $component['sort_order'],
            ]);
        }
    }

    private function generatedSku(string $name, ?StockItem $stockItem = null): string
    {
        $base = Str::slug($name);
        $base = Str::substr($base !== '' ? $base : 'stock-item', 0, 120);
        $candidate = $base;
        $suffix = 2;

        while (StockItem::query()
            ->when($stockItem?->exists, fn ($query) => $query->where('id', '!=', $stockItem->id))
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower($candidate)])
            ->exists()) {
            $suffixText = '-'.$suffix++;
            $candidate = Str::substr($base, 0, 120 - strlen($suffixText)).$suffixText;
        }

        return $candidate;
    }

    /** @param array<string, mixed> $validated
     *  @return array{stock_item_group_id: ?int, variant_name: ?string}
     */
    private function validatedVariantFields(array $validated, ?StockItem $stockItem): array
    {
        $groupId = isset($validated['stock_item_group_id']) && $validated['stock_item_group_id'] !== ''
            ? (int) $validated['stock_item_group_id']
            : null;
        $variantName = trim((string) ($validated['variant_name'] ?? ''));

        if ($groupId === null && $variantName !== '') {
            throw ValidationException::withMessages(['variant_name' => 'Choose a variant group before entering a variant name.']);
        }
        if ($groupId !== null && $variantName === '') {
            throw ValidationException::withMessages(['variant_name' => 'Enter a name for this variant.']);
        }
        if ($groupId !== null && StockItem::query()
            ->where('stock_item_group_id', $groupId)
            ->where('variant_name', $variantName)
            ->when($stockItem?->exists, fn ($query) => $query->where('id', '!=', $stockItem->id))
            ->exists()) {
            throw ValidationException::withMessages(['variant_name' => 'This group already has a variant with that name.']);
        }

        return [
            'stock_item_group_id' => $groupId,
            'variant_name' => $variantName !== '' ? $variantName : null,
        ];
    }

    /** @return array<int, array{id: int, supplier: string, label: string, summary: string, detail: string, search: string, total_cost_ex_tax: string, date: ?string}> */
    private function receiptExpenseOptions(): array
    {
        return Expense::query()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'supplier', 'description', 'paid_on', 'total_amount'])
            ->map(function (Expense $expense): array {
                $supplier = trim((string) $expense->supplier);
                $description = trim((string) $expense->description);
                $netAmount = max(0, round((float) $expense->total_amount - (float) $expense->gst_amount, 2));
                $amount = '$'.number_format($netAmount, 2).' ex GST';
                $date = $expense->paid_on?->format('j M Y') ?? 'Date not set';
                $detail = implode(' · ', [
                    $supplier !== '' ? $supplier : 'No supplier',
                    $description !== '' ? $description : 'No description',
                    $amount,
                    $date,
                ]);

                return [
                    'id' => (int) $expense->id,
                    'supplier' => $supplier,
                    'label' => 'Expense #'.$expense->id,
                    'summary' => 'Expense #'.$expense->id.' · '.$detail,
                    'detail' => $detail,
                    'search' => implode(' ', [$expense->id, $supplier, $description, $amount, $date]),
                    'total_cost_ex_tax' => number_format($netAmount, 2, '.', ''),
                    'date' => $expense->paid_on?->toDateString(),
                ];
            })
            ->all();
    }

    private function receiptTotalCost(mixed $value, ?Expense $expense, string $errorKey = 'total_cost_ex_tax'): float
    {
        if ($value !== null && trim((string) $value) !== '') {
            return (float) $value;
        }

        if ($expense instanceof Expense) {
            return max(0, round((float) $expense->total_amount - (float) $expense->gst_amount, 2));
        }

        throw ValidationException::withMessages([
            $errorKey => 'Enter the total cost excluding GST, or link an expense to use its full ex-GST amount.',
        ]);
    }

}
