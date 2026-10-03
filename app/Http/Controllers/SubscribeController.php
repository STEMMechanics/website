<?php

namespace App\Http\Controllers;

use App\Models\EmailSubscriptions;
use App\Models\SentEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SubscribeController extends Controller
{
    public function show(string $email): View|RedirectResponse
    {
        $emailModel = SentEmail::where('id', $email)->first();

        if (! $emailModel) {
            return redirect()->route('index')->with([
                'message' => 'The unsubscribe link is invalid or has expired.',
                'message-title' => 'Invalid Unsubscribe Link',
                'message-type' => 'warning',
            ]);
        }

        return view('unsubscribe', [
            'actionUrl' => route('unsubscribe', ['email' => $email]),
            'alreadyUnsubscribed' => ! EmailSubscriptions::where('email', $emailModel->recipient)->exists(),
        ]);
    }

    public function destroy(string $email): Response
    {
        $emailModel = SentEmail::where('id', $email)->first();

        if (! $emailModel) {
            return response('Invalid unsubscribe link.', 404);
        }

        $deleted = EmailSubscriptions::where('email', $emailModel->recipient)->delete();

        return response($deleted > 0 ? 'Unsubscribed.' : 'Already unsubscribed.', 200);
    }
}
