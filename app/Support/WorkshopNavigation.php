<?php

namespace App\Support;

use App\Models\Workshop;
use App\Services\WorkshopFollowUp;
use App\Services\Finance\WorkshopAllocation;
use App\Services\StockInventoryService;

class WorkshopNavigation
{
    public static function state(Workshop $workshop): array
    {
        return app(RequestMemo::class)->remember('workshop-navigation:'.$workshop->id, fn () => app(WorkshopAllocation::class)->state($workshop));
    }

    public static function tabs(Workshop $workshop): array
    {
        $state = self::state($workshop);
        $routeName = (string) request()->route()?->getName();
        $mediaRouteNames = ['admin.workshop.files', 'admin.workshop.photos', 'admin.workshop.media'];
        $isMediaPage = in_array($routeName, $mediaRouteNames, true);
        $mediaSection = (string) request()->query('section', 'files');
        $followUp = app(WorkshopFollowUp::class);
        $needsAttendance = $followUp->needsAttendance($workshop);
        $hasStockPlan = $workshop->stock_reconciled_at !== null || $followUp->hasStockPlan($workshop);
        $needsStock = $workshop->stock_reconciled_at === null
            && $followUp->hasEnded($workshop)
            && $hasStockPlan;
        $hasReservationShortages = app(StockInventoryService::class)->workshopReservationShortages($workshop) !== [];
        $routes = [
            'Create' => ['route' => 'edit', 'icon' => 'fa-solid fa-pen-to-square', 'active' => $routeName === 'admin.workshop.edit'],
            'Allocate' => ['route' => 'allocation.edit', 'icon' => 'fa-solid fa-list-check', 'active' => $routeName === 'admin.workshop.allocation.edit', 'attention' => ($state['ready'] && ! $state['current']) || $state['status'] === 'Allocation needs review', 'attention_label' => 'Allocation needs review'],
            'Run sheet' => ['route' => 'run-sheet', 'icon' => 'fa-solid fa-clipboard-list', 'active' => $routeName === 'admin.workshop.run-sheet', 'attention' => $hasReservationShortages, 'attention_label' => 'Pick list stock is short for this workshop'],
            'Attendance' => ['route' => 'attendance', 'icon' => 'fa-solid fa-user-check', 'active' => $routeName === 'admin.workshop.attendance', 'attention' => $needsAttendance, 'attention_label' => 'Attendance still needs recording'],
            'Files' => ['route' => 'media', 'section' => 'files', 'icon' => 'fa-regular fa-file-lines', 'active' => $isMediaPage && ($routeName === 'admin.workshop.files' || $mediaSection !== 'photos')],
            'Photos' => ['route' => 'media', 'section' => 'photos', 'icon' => 'fa-solid fa-images', 'active' => $isMediaPage && ($routeName === 'admin.workshop.photos' || $mediaSection === 'photos')],
        ];
        if ($hasStockPlan) {
            $routes['Reconcile stock'] = ['route' => 'stock-reconciliation', 'icon' => 'fa-solid fa-box-open', 'active' => $routeName === 'admin.workshop.stock-reconciliation', 'attention' => $needsStock, 'attention_label' => 'Workshop stock still needs reconciliation'];
        }

        return collect($routes)
            ->map(function ($definition, $title) use ($workshop): array {
                $routeParameters = ['workshop' => $workshop];
                if (isset($definition['section'])) {
                    $routeParameters['section'] = $definition['section'];
                }

                return [
                    'title' => $title,
                    'route' => route('admin.workshop.'.$definition['route'], $routeParameters),
                    'icon' => $definition['icon'],
                    'icon_only' => true,
                    'active' => $definition['active'],
                    'attention' => (bool) ($definition['attention'] ?? false),
                    'attention_label' => $definition['attention_label'] ?? null,
                ];
            })->values()->all();
    }
}
