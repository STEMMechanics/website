<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index() {
        return view('home', [
            'workshops' => Workshop::latest()->limit(4)->get()
        ]);
    }
}
