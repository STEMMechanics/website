<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    // Show all listings
    public function index() {
        return view('workshops.index', [
            'workshops' => Workshop::latest()->filter(request(['tag', 'search']))->paginate(8)
        ]);
    }
}
