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
    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $view = in_array($request->input('view'), ['upcoming', 'sent', 'failed', 'all'], true)
            ? (string) $request->input('view')
            : 'upcoming';

        $request->query->set('view', $view);
        $query = Reminder::query()->with([
            'recipient',
            'remindable' => function ($morphTo): void {
                $morphTo->morphWith([
                    Workshop::class => ['location'],
                ]);
            },
        ]);
        app(\App\Services\SiteListControls::class)->capturePresetCounts($query);
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

        app(\App\Services\SiteListControls::class)->apply($query);
        if ($request->expectsJson() && $request->boolean('select_listing')) {
            $ids = (clone $query)->limit(5001)->pluck('id');
            abort_if($ids->count() > 5000, 422, 'Select up to 5,000 reminders at a time. Narrow the filters and try again.');
            return response()->json(['names' => $ids->map(fn ($id) => (string) $id)])->header('Cache-Control', 'no-store');
        }

        return view('admin.reminder.index', [
            'reminders' => ($view === 'upcoming' ? $query->orderBy('scheduled_at') : $query->orderByDesc('sent_at'))->paginate(\App\Support\ListPageSize::resolve(30))->onEachSide(1),
            'selectedView' => $view,
        ]);
    }

    public function bulkEditor(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['reminder_ids' => ['required', 'array', 'min:1', 'max:5000'], 'reminder_ids.*' => ['required', 'integer', 'distinct', 'exists:reminders,id']]);
        return response()->json(['html' => view('admin.reminder.bulk-edit', ['ids' => $data['reminder_ids']])->render()]);
    }

    public function bulkUpdate(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'reminder_ids' => ['required', 'array', 'min:1', 'max:5000'],
            'reminder_ids.*' => ['required', 'integer', 'distinct', 'exists:reminders,id'],
            'action' => ['required', \Illuminate\Validation\Rule::in(['cancel', 'requeue'])],
        ]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($data): void {
            $reminders = Reminder::whereKey($data['reminder_ids'])->lockForUpdate()->get();
            foreach ($reminders as $reminder) {
                if ($data['action'] === 'requeue') {
                    if ($reminder->isCompletedWorkshopTask() || ($reminder->remindable instanceof Workshop && $reminder->remindable->status === 'cancelled')) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['action' => 'Completed tasks and cancelled workshops cannot be requeued. Remove those reminders from your selection.']);
                    }
                    $reminder->update([
                        'status' => Reminder::STATUS_PENDING,
                        'scheduled_at' => $reminder->scheduled_at?->isFuture() ? $reminder->scheduled_at : now(),
                        'queued_at' => null, 'sent_at' => null, 'failed_at' => null, 'failure_message' => null,
                    ]);
                } else {
                    $reminder->update(['status' => Reminder::STATUS_CANCELLED, 'queued_at' => null]);
                }
            }
        });
        return response()->json(['message' => count($data['reminder_ids']).' reminders '.($data['action'] === 'cancel' ? 'cancelled.' : 'requeued for their scheduled time or the next scheduler run if overdue.')]);
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
