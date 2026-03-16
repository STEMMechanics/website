<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $searchWords = collect(preg_split('/\s+/', $search) ?: [])
            ->map(fn ($word) => trim((string) $word))
            ->filter()
            ->values();

        $workshopQuery = Workshop::query()->publiclyVisible();

        if ($searchWords->isEmpty()) {
            $workshops = $workshopQuery
                ->whereRaw('1 = 0')
                ->paginate(6, ['*'], 'workshop')
                ->onEachSide(1);

            return view('search', [
                'workshops' => $workshops,
                'search' => $search,
            ]);
        }

        $workshopQuery->where(function ($query) use ($searchWords) {
            foreach ($searchWords as $word) {
                $query->orWhere(function ($subQuery) use ($word) {
                    $subQuery->where('title', 'like', '%'.$word.'%')
                        ->orWhere('content', 'like', '%'.$word.'%')
                        ->orWhereHas('location', function ($locationQuery) use ($word) {
                            $locationQuery->where('name', 'like', '%'.$word.'%');
                        });
                });
            }
        });

        $workshops = $workshopQuery->orderBy('starts_at', 'desc')
            ->paginate(6, ['*'], 'workshop')
            ->onEachSide(1);

//        $postQuery = Post::query()->where('status', 'published');
//        $postQuery->where(function ($query) use ($search_words) {
//            foreach ($search_words as $word) {
//                $query->where(function ($subQuery) use ($word) {
//                    $subQuery->where('title', 'like', '%' . $word . '%')
//                        ->orWhere('content', 'like', '%' . $word . '%');
//                });
//            }
//        });
//
//        $posts = $postQuery->orderBy('created_at', 'desc')
//            ->paginate(6, ['*'], 'post')
//            ->onEachSide(1);

        return view('search', [
            'workshops' => $workshops,
//            'posts'     => $posts,
            'search' => $search,
        ]);
    }
}
