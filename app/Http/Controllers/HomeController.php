<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Workshop;

class HomeController extends Controller
{
    public function index()
    {
        $posts = Post::query()->orderBy('created_at', 'desc')->limit(4)->get();
        $workshops = Workshop::query()->where('starts_at', '>', now())->orderBy('created_at', 'asc')->limit(4)->get();

        return view('home', [
            'posts' => $posts,
            'workshops' => $workshops,
        ]);
    }
}
