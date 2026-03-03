<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class SiteOption extends Model
{
    use HasFactory;

    /**
     * @return array<string, array{value: string, description: string, input_type?: string}>
     */
    public static function defaultDefinitions(): array
    {
        return [
            'app.notice' => [
                'value' => '',
                'description' => 'Optional notice bar shown across the site.',
            ],
            'document.business-info' => [
                'value' => "STEMMechanics\n63 Dalton Street\nWestcourt, QLD, 4870\nABN 15 772 281 735\n\n0400 130 190\nhello@stemmechanics.com.au\nstemmechanics.com.au",
                'description' => 'Business contact block shown on PDF documents.',
            ],
            'document.footer.payment' => [
                'value' => 'We accept cash, bank transfer and credit cards (Over the phone payments attract a 2.5% fee).',
                'description' => 'Footer payment text for invoice, quote, and tax adjustment PDFs.',
            ],
            'document.footer.terms' => [
                'value' => 'Payment terms are strictly 28 days from the invoice date. Long-term scheduled deliveries will be invoiced quarterly.',
                'description' => 'Footer terms text for invoice, quote, and tax adjustment PDFs.',
            ],
            'document.footer.travel' => [
                'value' => 'The first 30 minutes of travel is free; $28.00 every additional 15 minutes.',
                'description' => 'Footer travel text for invoice, quote, and tax adjustment PDFs.',
            ],
            'document.footer.questions' => [
                'value' => 'If you have any questions about this invoice, please feel free to contact us.',
                'description' => 'Footer questions text for invoice, quote, and tax adjustment PDFs.',
            ],
            'document.footer.bank-reference' => [
                'value' => 'Please include the invoice number as the payment description.',
                'description' => 'Bank-reference note shown in PDF document footers.',
            ],
            'checkout.bank-transfer-notice' => [
                'value' => 'Bank transfer details will be shown on the next screen.',
                'description' => 'Shown on ticket checkout when Bank Transfer is selected.',
            ],
            'checkout.pay-at-door-notice' => [
                'value' => 'EFTPOS and cash are available at the venue. Please bring correct change if paying by cash.',
                'description' => 'Shown on ticket checkout when Pay at Door is selected.',
            ],
            'payments.bank-account-name' => [
                'value' => 'STEMMechanics',
                'description' => 'Bank account name shown for bank transfer payments.',
            ],
            'payments.bank-bsb' => [
                'value' => '062-692',
                'description' => 'Bank BSB shown for bank transfer payments.',
            ],
            'payments.bank-account-number' => [
                'value' => '732-6629',
                'description' => 'Bank account number shown for bank transfer payments.',
            ],
            'tickets.hold-minutes' => [
                'value' => '10',
                'description' => 'Number of minutes ticket checkout holds remain reserved before expiring.',
                'input_type' => 'number',
            ],
            'users.restricted-usernames' => [
                'value' => 'stemcraft, stemmechanics, stemmech, admin, administrator, staff, mod, moderator, owner, support',
                'description' => 'Comma-separated username words blocked for non-admin accounts. Matching is done on whole username parts separated by dots, underscores, or hyphens.',
            ],
            'moderation.content-filter.enabled' => [
                'value' => '1',
                'description' => 'Master switch for configurable content filtering.',
                'input_type' => 'boolean',
            ],
            'moderation.content-filter.custom-patterns' => [
                'value' => '',
                'description' => 'One regex pattern per line, applied after the profanity package. Patterns are wrapped as case-insensitive Unicode regexes.',
            ],
            'moderation.content-filter.block-all-caps' => [
                'value' => '1',
                'description' => 'Block content that appears to be written entirely in capital letters above the minimum length.',
                'input_type' => 'boolean',
            ],
            'moderation.content-filter.min-all-caps-letters' => [
                'value' => '12',
                'description' => 'Minimum number of letters before an all-caps message is blocked.',
                'input_type' => 'number',
            ],
            'moderation.content-filter.max-repeated-character-run' => [
                'value' => '6',
                'description' => 'Maximum allowed run of the same letter or number before content is blocked.',
                'input_type' => 'number',
            ],
            'moderation.content-filter.max-repeated-word-run' => [
                'value' => '4',
                'description' => 'Maximum allowed run of the same repeated word before content is blocked.',
                'input_type' => 'number',
            ],
            'minecraft.server-webhook-url' => [
                'value' => '',
                'description' => 'Outbound webhook URL used to sync STEMCraft whitelist and punishment changes to the server plugin. Example: http://play.example.com:8125/stemcraft/webhook',
            ],
            'minecraft.webhook-secret' => [
                'value' => '',
                'description' => 'Shared secret used to sign outbound STEMCraft sync requests and verify inbound server webhook calls. Use a long random value, for example from `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.',
            ],
            'minecraft.rcon-host' => [
                'value' => '127.0.0.1',
                'description' => 'RCON host used by the admin STEMCraft console. Usually 127.0.0.1 when the Laravel app can reach the server directly.',
            ],
            'minecraft.rcon-port' => [
                'value' => '25575',
                'description' => 'RCON TCP port for the Minecraft server.',
                'input_type' => 'number',
            ],
            'minecraft.rcon-password' => [
                'value' => '',
                'description' => 'RCON password for the Minecraft server. Keep this private.',
            ],
            'minecraft.rcon-timeout-seconds' => [
                'value' => '5',
                'description' => 'RCON network timeout in seconds.',
                'input_type' => 'number',
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

    public static function defaultDescription(string $name): ?string
    {
        return static::defaultDefinitions()[$name]['description'] ?? null;
    }

    public static function inputType(string $name): string
    {
        return static::defaultDefinitions()[$name]['input_type'] ?? 'textarea';
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
                ['value' => (string) $definition['value']],
            );
        }
    }

    public static function resetAllToDefaults(): void
    {
        static::query()->delete();

        foreach (static::defaultDefinitions() as $name => $definition) {
            static::query()->create(
                [
                    'name' => $name,
                    'value' => (string) $definition['value'],
                ]
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
