<?php

namespace App\Traits;

use Illuminate\Mail\Mailables\Headers;

trait HasUnsubscribeLink
{
    protected ?string $unsubscribeLink = null;

    public function withUnsubscribeLink(string $link): static
    {
        $this->unsubscribeLink = $link;

        return $this;
    }

    public function headers(): Headers
    {
        $textHeaders = [];

        if ($this->unsubscribeLink !== null && $this->unsubscribeLink !== '') {
            $textHeaders['List-Unsubscribe'] = '<'.$this->unsubscribeLink.'>';
        }

        return new Headers(text: $textHeaders);
    }
}
