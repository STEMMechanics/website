<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request) {
        return view('account.index');
    }

    public function users_index(Request $request) {
        return view('account.users-index', [
            'users' => User::latest()->paginate(10),
        ]);
    }

    public function users_show(Request $request, User $user) {
        return view('account.users-show', [
            'user' => $user,
        ]);
    }

    public function media_index(Request $request) {
        return view('account.media-index');
    }
}
