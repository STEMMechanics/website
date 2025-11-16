<?php

namespace App\Http\Controllers;

use App\Models\EmailSubscriptions;
use App\Models\SentEmail;
use Illuminate\Http\Request;

class SubscribeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function destroy($email)
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
}
