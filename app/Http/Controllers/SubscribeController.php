<?php

namespace App\Http\Controllers;

use App\Models\EmailSubscriptions;
use App\Models\ForumTopicUserState;
use App\Models\SentEmail;
use App\Models\User;

class SubscribeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function destroy(string $email)
    {
        $emailModel = SentEmail::where('id', $email)->first();

        if (!$emailModel) {
            // Email not found, redirect to home page with a message
            return redirect()->route('index')->with([
                'message' => 'The unsubscribe link is invalid or has expired.',
                'message-title' => 'Invalid Unsubscribe Link',
                'message-type' => 'warning'
            ]);
        }

        // Existing unsubscribe logic
        $subscriptions = EmailSubscriptions::where('email', $emailModel->recipient)->get();

        if ($subscriptions->isEmpty()) {
            session()->flash('message', 'You are already unsubscribed.');
            session()->flash('message-title', 'Already Unsubscribed');
            session()->flash('message-type', 'info');
        } else {
            EmailSubscriptions::where('email', $emailModel->recipient)->delete();

            session()->flash('message', 'You have been successfully unsubscribed.');
            session()->flash('message-title', 'Unsubscribed');
            session()->flash('message-type', 'success');
        }

        return redirect()->route('index');
    }

    public function destroyDiscussionNotifications(string $email)
    {
        $emailModel = SentEmail::where('id', $email)->first();

        if (! $emailModel) {
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
            session()->flash('message', 'You have been unsubscribed from all discussion notifications.');
            session()->flash('message-title', 'Unsubscribed');
            session()->flash('message-type', 'success');
        } else {
            session()->flash('message', 'You are already unsubscribed from discussion notifications.');
            session()->flash('message-title', 'Already unsubscribed');
            session()->flash('message-type', 'info');
        }

        return redirect()->route('index');
    }
}
