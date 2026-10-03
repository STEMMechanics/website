<?php

namespace App\Services\Finance;

use App\Models\SiteOption;

class SponsorshipTaxService
{
    public const TAXABLE = 'taxable';
    public const GST_FREE_EXPORT = 'gst_free_export';
    public const NO_GST = 'no_gst';

    /** @return array{subtotal: float, gst_amount: float, total: float, tax_rate: float, treatment: string, tax_code: string} */
    public function breakdown(float $total, string $country, bool $nonResidentDeclaration): array
    {
        $total = round(max(0, $total), 2);
        $normalizedCountry = strtolower(trim($country));
        $isAustralia = in_array($normalizedCountry, ['au', 'aus', 'australia'], true);
        $exportEnabled = SiteOption::booleanValue('sponsorship.tax.gst-free-exports-enabled', true);
        $treatment = $isAustralia
            ? self::TAXABLE
            : ($exportEnabled && $nonResidentDeclaration ? self::GST_FREE_EXPORT : self::NO_GST);
        $rate = $treatment === self::TAXABLE
            ? max(0, (float) SiteOption::value('finance.gst-rate', '0.10'))
            : 0.0;
        $gst = $rate > 0 ? round($total - ($total / (1 + $rate)), 2) : 0.0;
        $subtotal = round($total - $gst, 2);

        return [
            'subtotal' => $subtotal,
            'gst_amount' => $gst,
            'total' => $total,
            'tax_rate' => $rate,
            'treatment' => $treatment,
            'tax_code' => match ($treatment) {
                self::TAXABLE => 'AU_GST_'.number_format($rate * 100, 2, '.', ''),
                self::GST_FREE_EXPORT => 'GST_FREE_EXPORT',
                default => 'NO_GST',
            },
        ];
    }
}
