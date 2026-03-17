<?php

namespace App\Http\Controllers;

use App\Models\EmailSubscriptions;
use App\Models\ForumTopicUserState;
use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Http\Request;

class SubscribeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function destroy(Request $request, string $email)
    {
        $emailModel = SentEmail::where('id', $email)->first();

        if (! $emailModel) {
            if ($request->isMethod('post')) {
                return response('Invalid unsubscribe link.', 404);
            }

            return redirect()->route('index')->with([
                'message' => 'The unsubscribe link is invalid or has expired.',
                'message-title' => 'Invalid Unsubscribe Link',
                'message-type' => 'warning',
            ]);
        }

        $subscriptions = EmailSubscriptions::where('email', $emailModel->recipient)->get();

        if ($subscriptions->isEmpty()) {
            if ($request->isMethod('post')) {
                return response('Already unsubscribed.', 200);
            }

            session()->flash('message', 'You are already unsubscribed.');
            session()->flash('message-title', 'Already Unsubscribed');
            session()->flash('message-type', 'info');
        } else {
            EmailSubscriptions::where('email', $emailModel->recipient)->delete();

            if ($request->isMethod('post')) {
                return response('Unsubscribed.', 200);
            }

            session()->flash('message', 'You have been successfully unsubscribed.');
            session()->flash('message-title', 'Unsubscribed');
            session()->flash('message-type', 'success');
        }

        return redirect()->route('index');
    }

    public function destroyDiscussionNotifications(Request $request, string $email)
    {
        $emailModel = SentEmail::where('id', $email)->first();

        if (! $emailModel) {
            if ($request->isMethod('post')) {
                return response('Invalid unsubscribe link.', 404);
            }

            return redirect()->route('index')->with([
                'message' => 'The unsubscribe link is invalid or has expired.',
                'message-title' => 'Invalid Unsubscribe Link',
                'message-type' => 'warning',
            ]);
        }

        $userIds = User::query()
            ->where('email', $emailModel->recipient)
            ->pluck('id');

        if ($userIds->isEmpty()) {
            if ($request->isMethod('post')) {
                return response('Nothing to unsubscribe.', 200);
            }

            session()->flash('message', 'No account was found for that email address.');
            session()->flash('message-title', 'Nothing to unsubscribe');
            session()->flash('message-type', 'info');

            return redirect()->route('index');
        }

        $updated = ForumTopicUserState::query()
            ->whereIn('user_id', $userIds->all())
            ->where('notifications_enabled', true)
            ->update([
                'notifications_enabled' => false,
            ]);

        if ($updated > 0) {
            if ($request->isMethod('post')) {
                return response('Unsubscribed.', 200);
            }

            session()->flash('message', 'You have been unsubscribed from all discussion notifications.');
            session()->flash('message-title', 'Unsubscribed');
            session()->flash('message-type', 'success');
        } else {
            if ($request->isMethod('post')) {
                return response('Already unsubscribed.', 200);
            }

            session()->flash('message', 'You are already unsubscribed from discussion notifications.');
            session()->flash('message-title', 'Already unsubscribed');
            session()->flash('message-type', 'info');
        }

        return redirect()->route('index');
    }
}
