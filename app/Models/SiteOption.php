<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class SiteOption extends Model
{
    use HasFactory;

    /**
     * @return array<string, array{value: string, description: string}>
     */
    public static function defaultDefinitions(): array
    {
        return [
            'document-business-info' => [
                'value' => "STEMMechanics\n63 Dalton Street\nWestcourt, QLD, 4870\nABN 15 772 281 735\n\n0400 130 190\nhello@stemmechanics.com.au\nstemmechanics.com.au",
                'description' => 'Business contact block shown on PDF documents.',
            ],
            'document-footer-payment' => [
                'value' => 'We accept cash, bank transfer and credit cards (Over the phone payments attract a 2.5% fee).',
                'description' => 'Footer payment text for invoice, quote, and tax adjustment PDFs.',
            ],
            'document-footer-terms' => [
                'value' => 'Payment terms are strictly 28 days from the invoice date. Long-term scheduled deliveries will be invoiced quarterly.',
                'description' => 'Footer terms text for invoice, quote, and tax adjustment PDFs.',
            ],
            'document-footer-travel' => [
                'value' => 'The first 30 minutes of travel is free; $28.00 every additional 15 minutes.',
                'description' => 'Footer travel text for invoice, quote, and tax adjustment PDFs.',
            ],
            'document-footer-questions' => [
                'value' => 'If you have any questions about this invoice, please feel free to contact us.',
                'description' => 'Footer questions text for invoice, quote, and tax adjustment PDFs.',
            ],
            'document-footer-bank-reference' => [
                'value' => 'Please include the invoice number as the payment description.',
                'description' => 'Bank-reference note shown in PDF document footers.',
            ],
            'checkout.bank_transfer_notice' => [
                'value' => 'Bank transfer details will be shown on the next screen.',
                'description' => 'Shown on ticket checkout when Bank Transfer is selected.',
            ],
            'checkout.pay_at_door_notice' => [
                'value' => 'EFTPOS and cash are available at the venue. Please bring correct change if paying by cash.',
                'description' => 'Shown on ticket checkout when Pay at Door is selected.',
            ],
            'payments.bank_account_name' => [
                'value' => 'STEMMechanics',
                'description' => 'Bank account name shown for bank transfer payments.',
            ],
            'payments.bank_bsb' => [
                'value' => '062-692',
                'description' => 'Bank BSB shown for bank transfer payments.',
            ],
            'payments.bank_account_number' => [
                'value' => '732-6629',
                'description' => 'Bank account number shown for bank transfer payments.',
            ],
        ];
    }

    public static function hasDefault(string $name): bool
    {
        return array_key_exists($name, static::defaultDefinitions());
    }

    public static function defaultValue(string $name): ?string
    {
        return static::defaultDefinitions()[$name]['value'] ?? null;
    }

    public static function resetToDefault(string $name): ?self
    {
        if (! static::hasDefault($name)) {
            return null;
        }

        return tap(static::query()->firstOrNew(['name' => $name]), function (self $option) use ($name): void {
            $option->value = (string) static::defaultValue($name);
            $option->save();
        });
    }

    public static function ensureDefaultOptionsExist(): void
    {
        foreach (static::defaultDefinitions() as $name => $definition) {
            static::query()->firstOrCreate(
                ['name' => $name],
                ['value' => (string) ($definition['value'] ?? '')],
            );
        }
    }

    public static function resetAllToDefaults(): void
    {
        foreach (static::defaultDefinitions() as $name => $definition) {
            static::query()->updateOrCreate(
                ['name' => $name],
                ['value' => (string) ($definition['value'] ?? '')],
            );
        }
    }

    protected $fillable = [
        'name',
        'value',
    ];

    public function getValueToHtmlAttribute(): HtmlString
    {
        return new HtmlString(nl2br(e((string) ($this->value ?? ''))));
    }

    public static function value(string $name, ?string $default = null): ?string
    {
        $value = static::query()
            ->where('name', $name)
            ->value('value');

        return $value ?? $default;
    }

    public static function valueToHtml(string $name, ?string $default = null): HtmlString
    {
        return new HtmlString(nl2br(e((string) static::value($name, $default))));
    }
}
