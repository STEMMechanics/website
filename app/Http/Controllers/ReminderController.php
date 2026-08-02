<?php

namespace App\Http\Controllers;

use App\Jobs\SendReminder;
use App\Models\Reminder;
use App\Models\Workshop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        $view = in_array($request->input('view'), ['upcoming', 'sent', 'failed', 'all'], true)
            ? (string) $request->input('view')
            : 'upcoming';

        $query = Reminder::query()->with([
            'recipient',
            'remindable' => function ($morphTo): void {
                $morphTo->morphWith([
                    Workshop::class => ['location'],
                ]);
            },
        ]);
        match ($view) {
            'upcoming' => $query->whereIn('status', [Reminder::STATUS_PENDING, Reminder::STATUS_QUEUED]),
            'sent' => $query->where('status', Reminder::STATUS_SENT),
            'failed' => $query->where('status', Reminder::STATUS_FAILED),
            default => null,
        };

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn ($builder) => $builder
                ->where('subject', 'like', '%'.$search.'%')
                ->orWhere('message', 'like', '%'.$search.'%')
                ->orWhere('recipient_email', 'like', '%'.$search.'%'));
        }

        return view('admin.reminder.index', [
            'reminders' => ($view === 'upcoming' ? $query->orderBy('scheduled_at') : $query->orderByDesc('sent_at'))->paginate(30)->onEachSide(1),
            'selectedView' => $view,
        ]);
    }

    public function sendNow(Reminder $reminder): RedirectResponse
    {
        if (! in_array($reminder->status, [Reminder::STATUS_PENDING, Reminder::STATUS_QUEUED, Reminder::STATUS_FAILED, Reminder::STATUS_SENT], true)) {
            return redirect()->back()->with([
                'message' => 'Cancelled reminders cannot be sent.',
                'message-title' => 'Reminder not sent',
                'message-type' => 'warning',
            ]);
        }

        $reminder->update([
            'status' => Reminder::STATUS_QUEUED,
            'queued_at' => now(),
            'failed_at' => null,
            'failure_message' => null,
            'sent_at' => null,
        ]);

        $job = new SendReminder((int) $reminder->id);
        try {
            $job->handle();
        } catch (Throwable $exception) {
            report($exception);
            $job->failed($exception);

            return redirect()->back()->with([
                'message' => 'The reminder failed to send: '.$exception->getMessage(),
                'message-title' => 'Reminder not sent',
                'message-type' => 'danger',
            ]);
        }

        return redirect()->back()->with([
            'message' => 'The reminder was sent to '.$reminder->recipient_email.'.',
            'message-title' => 'Reminder sent',
            'message-type' => 'success',
        ]);
    }
}
