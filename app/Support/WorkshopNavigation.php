<?php

namespace App\Support;

use App\Models\Workshop;
use App\Services\Finance\WorkshopAllocation;

class WorkshopNavigation
{
    public static function state(Workshop $workshop): array
    {
        return app(RequestMemo::class)->remember('workshop-navigation:'.$workshop->id, fn () => app(WorkshopAllocation::class)->state($workshop));
    }

    public static function tabs(Workshop $workshop): array
    {
        $state = self::state($workshop);
        return collect(['Details' => 'edit', 'Attendance' => 'attendance', 'Allocation' => 'allocation.edit', 'Files' => 'files', 'Photos' => 'photos'])
            ->map(fn ($route, $title) => [
                'title' => $title,
                'route' => route('admin.workshop.'.$route, $workshop),
                'attention' => $title === 'Allocation' && (($state['ready'] && ! $state['current']) || $state['status'] === 'Allocation needs review'),
            ])->values()->all();
    }
}
