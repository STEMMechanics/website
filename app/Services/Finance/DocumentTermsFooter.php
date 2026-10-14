<?php

namespace App\Services\Finance;

use App\Models\SiteOption;

class DocumentTermsFooter
{
    public static function render(string $documentType): string
    {
        $suffix = match ($documentType) {
            'invoice' => 'invoice',
            'quote' => 'quote',
            'adjustment', 'tax adjustment' => 'adjustment',
            default => null,
        };

        if ($suffix !== null) {
            $override = SiteOption::query()->where('name', 'document.footer.terms.'.$suffix)->first(['value']);
            // Cleared form values are null; the existing option still overrides the fallback.
            if ($override !== null) {
                return trim((string) $override->value);
            }
        }

        return trim((string) SiteOption::value('document.footer.terms'));
    }
}
