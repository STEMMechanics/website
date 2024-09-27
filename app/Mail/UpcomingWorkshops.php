<?php

namespace App\Mail;

use App\Models\Workshop;
use App\Traits\HasUnsubscribeLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class UpcomingWorkshops extends Mailable
{
    use Queueable, SerializesModels, HasUnsubscribeLink;

    public $subject;
    public $email;
    public $workshops;

    public function __construct($email, $subject = 'Upcoming Workshops 🌟')
    {
        $this->subject = $subject;
        $this->email = $email;
        $this->workshops = $this->getUpcomingWorkshops();
    }

    private function getUpcomingWorkshops()
    {
        $startDate = Carbon::now()->addDays(3);
        $endDate = Carbon::now()->addDays(42);

        return Workshop::select('workshops.*', 'locations.name as location_name')
            ->join('locations', 'workshops.location_id', '=', 'locations.id')
            ->whereIn('workshops.status', ['open','scheduled'])
            ->whereBetween('workshops.starts_at', [$startDate, $endDate])
            ->where('locations.name', 'not like', '%private%')
            ->orderBy('locations.name')
            ->orderBy('workshops.starts_at')
            ->get();
    }

    public function build()
    {
        // Bail if there are no upcoming workshops
        if ($this->workshops->isEmpty()) {
            return false;
        }

        return $this
            ->subject($this->subject)
            ->markdown('emails.upcoming-workshops')
            ->with([
                'email' => $this->email,
                'workshops' => $this->workshops,
                'unsubscribeLink' => $this->unsubscribeLink
            ]);
    }
}
