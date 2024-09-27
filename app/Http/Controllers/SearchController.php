<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Workshop;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('q', '');
        $search_words = explode(' ', $search); // Split the search query into words[1]

        $workshopQuery = Workshop::query()->where('status', '!=', 'draft');

        $workshopQuery->where(function ($query) use ($search_words) {
            foreach ($search_words as $word) {
                $query->orWhere(function ($subQuery) use ($word) {
                    $subQuery->where('title', 'like', '%' . $word . '%')
                        ->orWhere('content', 'like', '%' . $word . '%')
                        ->orWhereHas('location', function ($locationQuery) use ($word) {
                            $locationQuery->where('name', 'like', '%' . $word . '%');
                        });
                });
            }
        });

        $workshops = $workshopQuery->orderBy('starts_at', 'desc')
            ->paginate(6, ['*'], 'workshop');

        $postQuery = Post::query()->where('status', 'published');
        $postQuery->where(function ($query) use ($search_words) {
            foreach ($search_words as $word) {
                $query->where(function ($subQuery) use ($word) {
                    $subQuery->where('title', 'like', '%' . $word . '%')
                        ->orWhere('content', 'like', '%' . $word . '%');
                });
            }
        });

        $posts = $postQuery->orderBy('created_at', 'desc')
            ->paginate(6, ['*'], 'post')
            ->onEachSide(1);

        return view('search', [
            'workshops' => $workshops,
            'posts'     => $posts,
            'search'    => $search,
        ]);
    }
}
