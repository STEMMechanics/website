<?php

namespace App\Traits;

trait HasUnsubscribeLink
{
    protected $unsubscribeLink;

    public function withUnsubscribeLink($link)
    {
        $this->unsubscribeLink = $link;
        return $this;
    }
}
