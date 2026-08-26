<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Models\Workshop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkshopRecommendationController extends Controller
{
    public function impression(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_workshop_id' => ['required', 'exists:workshops,id'],
            'workshop_ids' => ['required', 'array', 'max:3'],
            'workshop_ids.*' => ['required', 'exists:workshops,id'],
            'placement' => ['required', 'string', 'max:40'],
        ]);

        if (! (bool) (auth()->user()?->isAdmin() ?? false)) {
            foreach (array_unique($validated['workshop_ids']) as $workshopId) {
                $this->record($request, AnalyticsEvent::TYPE_RECOMMENDATION_IMPRESSION, (string) $workshopId, (string) $validated['source_workshop_id'], (string) $validated['placement']);
            }
        }

        return response()->json(['recorded' => true]);
    }

    public function click(Request $request, Workshop $source, Workshop $workshop): RedirectResponse
    {
        if (! (bool) (auth()->user()?->isAdmin() ?? false) && $workshop->isPubliclyVisible()) {
            $this->record($request, AnalyticsEvent::TYPE_RECOMMENDATION_CLICK, (string) $workshop->id, (string) $source->id, trim((string) $request->query('placement', 'workshop')));
        }

        return redirect()->route('workshop.show', $workshop);
    }

    private function record(Request $request, string $type, string $workshopId, string $sourceId, string $placement): void
    {
        $sessionToken = (string) $request->session()->get('analytics_session_token', '');
        if ($sessionToken === '') {
            $sessionToken = Str::lower(Str::random(40));
            $request->session()->put('analytics_session_token', $sessionToken);
        }

        AnalyticsEvent::query()->create([
            'event_type' => $type,
            'session_token' => $sessionToken,
            'path' => $request->getPathInfo(),
            'route_name' => (string) $request->route()?->getName(),
            'workshop_id' => $workshopId,
            'source_workshop_id' => $sourceId,
            'recommendation_placement' => Str::limit($placement, 40, ''),
            'http_method' => $request->method(),
            'created_at' => now(),
        ]);
    }
}
