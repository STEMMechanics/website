<?php

namespace App\Mail;

use App\Models\Workshop;
use App\Traits\HasUnsubscribeLink;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class UpcomingWorkshops extends Mailable
{
    use Queueable, SerializesModels, HasUnsubscribeLink;

    public $subject;
    public $email;
    public $heroHeader;
    public $heroCta;
    public $heroButtonLabel;
    public $heroSubject;
    public $onlineWorkshops;
    public $courses;
    public $workshops;

    public function __construct($email, $subject = 'Upcoming Workshops 🌟')
    {
        $this->subject = $subject;
        $this->email = $email;
        [$this->heroHeader, $this->heroCta, $this->heroSubject] = $this->selectHeroCopy();
        $this->heroButtonLabel = trim((string) config('newsletter.upcoming_workshops.button_label', 'View All Workshops')) ?: 'View All Workshops';
        $this->workshops = $this->getUpcomingWorkshops();
        $this->onlineWorkshops = $this->getUpcomingOnlineWorkshops();
        $this->courses = $this->getUpcomingCourses();
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function selectHeroCopy(): array
    {
        $copy = collect(config('newsletter.upcoming_workshops.hero_messages', []))
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item): array {
                return [
                    'header' => trim((string) ($item['header'] ?? '')),
                    'cta' => trim((string) ($item['cta'] ?? '')),
                ];
            })
            ->filter(fn (array $item): bool => $item['header'] !== '' && $item['cta'] !== '')
            ->values();

        if ($copy->isEmpty()) {
            return [
                'Fresh workshops are ready to book.',
                'Pick your next session, lock in your place, and keep the momentum going with something hands-on.',
                'Upcoming Workshops 🌟',
            ];
        }

        $selected = Arr::random($copy->all());

        return [
            (string) $selected['header'],
            (string) $selected['cta'],
            (string) ($selected['subject'] ?? 'Upcoming Workshops 🌟'),
        ];
    }

    private function baseUpcomingWorkshopsQuery(): Builder
    {
        $startDate = Carbon::now()->addDays(3);
        $endDate = Carbon::now()->addDays(42);

        return Workshop::query()
            ->with('location')
            ->publiclyVisible()
            ->where(function ($builder) {
                $builder->whereNull('workshops.is_private')
                    ->orWhere('workshops.is_private', false);
            })
            ->where('workshops.status', '!=', 'private')
            ->whereIn('workshops.status', ['open','scheduled'])
            ->whereBetween('workshops.starts_at', [$startDate, $endDate]);
    }

    private function getUpcomingWorkshops(): Collection
    {
        return $this->baseUpcomingWorkshopsQuery()
            ->with('location')
            ->whereNotNull('workshops.location_id')
            ->where(function ($builder) {
                $builder->whereNull('workshops.registration')
                    ->orWhere('workshops.registration', '!=', 'classroom');
            })
            ->orderBy('workshops.starts_at')
            ->get()
            ->values();
    }

    private function getUpcomingOnlineWorkshops(): Collection
    {
        return $this->baseUpcomingWorkshopsQuery()
            ->whereNull('workshops.location_id')
            ->where(function ($builder) {
                $builder->whereNull('workshops.registration')
                    ->orWhere('workshops.registration', '!=', 'classroom');
            })
            ->orderBy('workshops.starts_at')
            ->get();
    }

    private function getUpcomingCourses(): Collection
    {
        return $this->baseUpcomingWorkshopsQuery()
            ->where('workshops.registration', 'classroom')
            ->orderBy('workshops.starts_at')
            ->get();
    }

    public function build()
    {
        // Bail if there are no upcoming workshops or courses.
        if ($this->workshops->isEmpty() && $this->onlineWorkshops->isEmpty() && $this->courses->isEmpty()) {
            return false;
        }

        return $this
            ->subject($this->heroSubject ?: $this->subject)
            ->markdown('emails.upcoming-workshops')
            ->with([
                'email' => $this->email,
                'hideHeader' => true,
                'heroButtonLabel' => $this->heroButtonLabel,
                'heroCta' => $this->heroCta,
                'heroHeader' => $this->heroHeader,
                'heroSubject' => $this->heroSubject,
                'onlineWorkshops' => $this->onlineWorkshops,
                'courses' => $this->courses,
                'workshops' => $this->workshops,
                'unsubscribeLink' => $this->unsubscribeLink,
            ]);
    }
}
