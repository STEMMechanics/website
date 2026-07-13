<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use Illuminate\Http\Response;

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

        return response()
            ->view('sitemap.xml', [
                'pages' => $pages,
                'workshops' => $workshops,
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
