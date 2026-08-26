<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Workshop;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $pages = collect([
            ['loc' => route('index'), 'lastmod' => now()],
            ['loc' => route('workshop.index'), 'lastmod' => now()],
            ['loc' => route('workshop.past.index'), 'lastmod' => now()],
            ['loc' => route('stemcraft.index'), 'lastmod' => now()],
            ['loc' => route('stemcraft.join'), 'lastmod' => now()],
            ['loc' => route('stemcraft.rules'), 'lastmod' => now()],
            ['loc' => route('stemcraft.faqs'), 'lastmod' => now()],
            ['loc' => route('about'), 'lastmod' => null],
            ['loc' => route('contact'), 'lastmod' => null],
            ['loc' => route('privacy'), 'lastmod' => null],
            ['loc' => route('terms-conditions'), 'lastmod' => null],
            ['loc' => route('code-of-conduct'), 'lastmod' => null],
        ]);

        $workshops = Workshop::query()
            ->publiclyVisible()
            ->orderByDesc('updated_at')
            ->get();

        $suburbPages = Location::query()
            ->whereNotNull('suburb')
            ->where('suburb', '!=', '')
            ->whereIn('id', Workshop::query()
                ->publiclyVisible()
                ->where('is_private', false)
                ->whereNotNull('location_id')
                ->select('location_id'))
            ->get(['suburb', 'updated_at'])
            ->unique(fn (Location $location): string => Str::lower(trim((string) $location->suburb)))
            ->map(fn (Location $location): array => [
                'loc' => route('workshop.suburb', Str::slug((string) $location->suburb)),
                'lastmod' => $location->updated_at,
            ]);

        $pages = $pages->concat($suburbPages);

        return response()
            ->view('sitemap.xml', [
                'pages' => $pages,
                'workshops' => $workshops,
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
