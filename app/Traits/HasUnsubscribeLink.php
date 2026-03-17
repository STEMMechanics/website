<?php

namespace App\Traits;

trait HasUnsubscribeLink
{
    protected ?string $unsubscribeLink = null;

    public function withUnsubscribeLink(string $link): static
    {
        $this->unsubscribeLink = $link;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function unsubscribeHeaders(): array
    {
        $textHeaders = [];

        if ($this->unsubscribeLink !== null && $this->unsubscribeLink !== '') {
            $textHeaders['List-Unsubscribe'] = '<'.$this->unsubscribeLink.'>';

            if (str_starts_with($this->unsubscribeLink, 'https://')) {
                $textHeaders['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
            }
        }

        return $textHeaders;
    }
}
