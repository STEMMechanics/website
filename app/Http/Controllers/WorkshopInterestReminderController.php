<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use App\Models\WorkshopInterest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkshopInterestReminderController extends Controller
{
    public function unsubscribe(Request $request, WorkshopInterest $interest): View
    {
        $interest->loadMissing('workshop');
        $workshop = $interest->workshop;
        abort_unless($workshop instanceof Workshop, 404);

        if ($request->isMethod('post') && ! $interest->reminders_unsubscribed_at) {
            $interest->update([
                'reminders_unsubscribed_at' => now(),
                'two_day_reminder_queued_at' => null,
                'two_hour_reminder_queued_at' => null,
            ]);
            $interest->refresh();
        }

        return view('workshop.interest-reminder-unsubscribe', [
            'interest' => $interest,
            'workshop' => $workshop,
            'unsubscribed' => (bool) $interest->reminders_unsubscribed_at,
            'actionUrl' => $request->fullUrl(),
        ]);
    }
}
