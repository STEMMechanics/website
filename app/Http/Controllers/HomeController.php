<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Ticket;
use App\Models\Workshop;

class HomeController extends Controller
{
    public function index()
    {
        // $posts = Post::query()->orderBy('created_at', 'desc')->limit(4)->get();
        $workshops = Workshop::query()
            ->publiclyVisible()
            ->where('starts_at', '>', now())
            ->whereIn('status', ['open', 'scheduled', 'full'])
            ->withCount([
                'tickets as active_tickets_count' => fn ($query) => $query->whereIn('status', Ticket::activePurchasedStatuses()),
            ])
            ->orderBy('starts_at', 'asc')
            ->limit(4)
            ->get();

        return view('home', [
            // 'posts' => $posts,
            'workshops' => $workshops,
        ]);
    }
}
