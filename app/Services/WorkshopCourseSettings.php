<?php

namespace App\Services;

use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkshopCourseSettings
{
    /** @return array<string, mixed> */
    public function validated(Request $request, ?Workshop $workshop = null): array
    {
        // Older callers updating unrelated workshop fields retain the existing course settings.
        if (! $request->exists('format')) {
            return [];
        }
        $data = $request->validate([
            'format' => 'required|in:workshop,course',
            'course_sessions' => 'exclude_unless:format,course|nullable|array|max:104',
            'course_sessions.*.id' => 'nullable|uuid|distinct',
            'course_sessions.*.label' => 'nullable|string|max:120',
            'course_sessions.*.starts_at' => 'required|date',
            'course_sessions.*.ends_at' => 'required|date',
            'welcome_enabled' => 'nullable|boolean',
            'welcome_subject' => 'nullable|required_if:welcome_enabled,1|string|max:200',
            'welcome_body' => 'nullable|required_if:welcome_enabled,1|string|max:100000',
            'welcome_send_at' => 'nullable|date',
            'welcome_files' => 'nullable|array|max:20',
            'welcome_files.*' => 'string|distinct|exists:media,name',
        ]);
        $sessions = $data['format'] === 'course' ? ($data['course_sessions'] ?? []) : [];
        if ($data['format'] === 'course' && count($sessions) === 0) {
            throw ValidationException::withMessages(['course_sessions' => 'Add at least one course session.']);
        }
        $start = Carbon::parse($request->input('starts_at', $workshop?->starts_at));
        $end = Carbon::parse($request->input('ends_at', $workshop?->ends_at));
        $normalised = [];
        foreach ($sessions as $i => $session) {
            $from = Carbon::parse($session['starts_at']);
            $to = Carbon::parse($session['ends_at']);
            if ($to->lte($from) || $from->lt($start) || $to->gt($end)) {
                throw ValidationException::withMessages(["course_sessions.$i.starts_at" => 'Each session must finish after it starts and fit within the workshop start/end dates.']);
            }
            $normalised[] = ['id' => $session['id'] ?? (string) Str::uuid(), 'label' => $session['label'] ?? '',
                'starts_at' => $from->format('Y-m-d\TH:i'), 'ends_at' => $to->format('Y-m-d\TH:i')];
        }
        usort($normalised, fn (array $a, array $b) => strcmp($a['starts_at'], $b['starts_at']));
        foreach ($normalised as $i => $session) {
            if ($i && $session['starts_at'] < $normalised[$i - 1]['ends_at']) {
                throw ValidationException::withMessages(['course_sessions' => 'Course sessions cannot overlap.']);
            }
        }
        if ($workshop) {
            $recorded = DB::table('workshop_session_attendance')->where('workshop_id', $workshop->id)->pluck('session_id')->unique()->all();
            if (array_diff($recorded, array_column($normalised, 'id'))) {
                throw ValidationException::withMessages(['course_sessions' => 'A session with recorded attendance cannot be removed. Clear its attendance first.']);
            }
        }
        $firstStart = $normalised ? Carbon::parse($normalised[0]['starts_at']) : $start;
        $sendAt = empty($data['welcome_send_at']) ? $firstStart->copy()->subDays(3) : Carbon::parse($data['welcome_send_at']);
        if ($request->boolean('welcome_enabled') && $sendAt->gte($firstStart)) {
            throw ValidationException::withMessages(['welcome_send_at' => 'Schedule the welcome email before the first session starts.']);
        }

        return [
            'format' => $data['format'], 'course_sessions' => $normalised,
            'welcome_enabled' => $request->boolean('welcome_enabled'),
            'welcome_subject' => $data['welcome_subject'] ?? null, 'welcome_body' => $data['welcome_body'] ?? null,
            'welcome_send_at' => $sendAt,
        ];
    }
}
