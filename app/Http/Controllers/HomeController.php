<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Event;

class HomeController extends Controller
{
    public function index()
    {
        $posts = Post::query()->orderBy('created_at', 'desc')->limit(4)->get();
        $events = Event::query()->where('starts_at', '>', now())->where('status', '!=', 'private')->orderBy('starts_at', 'asc')->limit(4)->get();

        return view('home', [
            'posts' => $posts,
            'events' => $events,
        ]);
    }
}
