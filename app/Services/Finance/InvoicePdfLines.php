<?php

namespace App\Services\Finance;

class InvoicePdfLines
{
    public static function prepare(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (($item['kind'] ?? '') === 'travel' && isset($item['details_json']['travel']['billable_units']) && ($item['details_json']['travel']['quantity_basis'] ?? '') !== 'hours') {
                $item['quantity'] = $item['details_json']['travel']['billable_units'] / 4;
                $item['unit_price_ex_tax'] = ($item['unit_price_ex_tax'] ?? $item['unit_price'] ?? 0) * 4;
            }
            $result[] = $item;
        }
        return $result;
    }
}
