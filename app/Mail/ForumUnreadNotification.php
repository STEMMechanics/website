<?php

namespace App\Mail;

use App\Models\User;
use App\Traits\HasUnsubscribeLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ForumUnreadNotification extends Mailable
{
    use HasUnsubscribeLink, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $threadDigests,
    ) {}

    public function build()
    {
        $threadCount = $this->threadDigests->count();
        $totalUnreadPosts = $this->threadDigests->sum(fn ($digest) => $digest['posts']->count());
        $subject = $threadCount === 1
            ? 'New replies in discussion '.$this->threadDigests->first()['topic']->plainTitle()
            : 'New replies in '.$threadCount.' discussions';

        return $this
            ->subject($subject)
            ->markdown('emails.forum-unread-notification')
            ->with([
                'user' => $this->user,
                'threadDigests' => $this->threadDigests,
                'totalUnreadPosts' => $totalUnreadPosts,
                'unsubscribeLink' => $this->unsubscribeLink,
            ]);
    }
}
