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
    ) {}

    public function build()
    {
        return $this
            ->subject('Child forum activity: '.$this->childUsername)
            ->markdown('emails.child-forum-activity-notification')
            ->with([
                'parentName' => $this->parentName,
                'childUsername' => $this->childUsername,
                'activityLabel' => $this->activityLabel,
                'statusLabel' => $this->statusLabel,
                'categoryName' => $this->categoryName,
                'topicTitle' => $this->topicTitle,
                'preview' => $this->preview,
                'manageUrl' => $this->manageUrl,
            ]);
    }
}
