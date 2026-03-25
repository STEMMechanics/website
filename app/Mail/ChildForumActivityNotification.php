<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChildForumActivityNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $parentName,
        public readonly string $childUsername,
        public readonly string $activityLabel,
        public readonly string $statusLabel,
        public readonly string $categoryName,
        public readonly string $topicTitle,
        public readonly string $preview,
        public readonly string $manageUrl,
        public readonly ?string $approveUrl = null,
    ) {}

    public function build()
    {
        return $this
            ->subject('Child forum activity: '.$this->childUsername)
            ->view('emails.child-forum-activity-notification-html')
            ->text('emails.child-forum-activity-notification-text')
            ->with([
                'parentName' => $this->parentName,
                'childUsername' => $this->childUsername,
                'activityLabel' => $this->activityLabel,
                'statusLabel' => $this->statusLabel,
                'categoryName' => $this->categoryName,
                'topicTitle' => $this->topicTitle,
                'preview' => $this->preview,
                'manageUrl' => $this->manageUrl,
                'approveUrl' => $this->approveUrl,
            ]);
    }
}
