<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class StemcraftController extends Controller
{
    public function index(): View
    {
        return view('stemcraft.index');
    }

    public function join(): View
    {
        return view('stemcraft.join');
    }

    public function rules(): View
    {
        return view('stemcraft.rules');
    }

    public function faqs(): View
    {
        return view('stemcraft.faqs');
    }
}
