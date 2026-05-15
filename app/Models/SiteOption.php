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
            'backup.database.keep' => [
                'value' => '168',
                'description' => 'Number of database backup files to retain when database:backup runs without an explicit --keep value.',
                'input_type' => 'number',
            ],
            'backup.files.full.keep' => [
                'value' => '12',
                'description' => 'Number of full file backup runs to retain when files:backup --full runs without an explicit --keep value.',
                'input_type' => 'number',
            ],
            'backup.files.incremental.keep' => [
                'value' => '35',
                'description' => 'Number of incremental file backup runs to retain when files:backup --incremental runs without an explicit --keep value.',
                'input_type' => 'number',
            ],
            'backup.files.keep' => [
                'value' => '35',
                'description' => 'Legacy fallback retention used if the full or incremental file backup keep counts are not set.',
                'input_type' => 'number',
            ],
            'backup.remote.disk' => [
                'value' => '',
                'description' => 'Laravel filesystem disk used for offsite backups, for example an SFTP or S3 disk configured in config/filesystems.php.',
            ],
            'backup.remote.path' => [
                'value' => 'offsite-backups',
                'description' => 'Base folder on the remote backup disk where backup runs and manifests are written.',
            ],
            'backup.remote.include-database' => [
                'value' => '1',
                'description' => 'Whether offsite backups should include a full compressed database dump.',
                'input_type' => 'boolean',
            ],
            'backup.remote.include-files' => [
                'value' => '1',
                'description' => 'Whether offsite backups should include configured file sources.',
                'input_type' => 'boolean',
            ],
            'backup.remote.files-mode' => [
                'value' => 'incremental',
                'description' => 'File backup mode for offsite backups. Use full to upload every selected file each run, or incremental to upload only changed/new files while still taking a full database backup.',
            ],
            'backup.remote.file-sources' => [
                'value' => json_encode(\App\Services\ExternalBackupService::defaultFileSources(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]',
                'description' => 'JSON array of file sources for offsite backups. Each item should define key, disk, path, and label.',
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
            'store.shipping.satchels' => [
                'value' => json_encode(config('store.shipping.satchels', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]',
                'description' => 'JSON list of satchel options used by the store shipping calculator. Prefer editing this from the Store Settings admin page.',
            ],
            'store.shipping.max-satchel-weight-grams' => [
                'value' => (string) config('store.shipping.max_satchel_weight_grams', 5000),
                'description' => 'Maximum known packed weight allowed in a satchel before the cart is split into another parcel.',
                'input_type' => 'number',
            ],
            'store.shipping.boxed-shipping-label' => [
                'value' => (string) config('store.shipping.boxed_shipping.label', 'Boxed shipping required'),
                'description' => 'Label shown when an order must ship in a box instead of satchels.',
            ],
            'store.shipping.boxed-shipping-message' => [
                'value' => (string) config('store.shipping.boxed_shipping.message', 'This order cannot be packed into satchels and needs boxed shipping.'),
                'description' => 'Message shown when boxed shipping is required.',
            ],
            'store.shipping.boxed-shipping-amount' => [
                'value' => config('store.shipping.boxed_shipping.amount') !== null ? (string) config('store.shipping.boxed_shipping.amount') : '',
                'description' => 'Optional boxed shipping charge. Leave blank to require a manual quote.',
                'input_type' => 'number',
            ],
            'store.shipping.tracking-link-templates' => [
                'value' => '{}',
                'description' => 'JSON object of courier-name keys to tracking URL templates. Use {tracking} as the placeholder for the parcel number.',
            ],
            'store.public-enabled' => [
                'value' => '1',
                'description' => 'Master switch for the public store storefront, cart, and checkout. Existing order links still work when disabled.',
                'input_type' => 'boolean',
            ],
            'store.order.ready-for-pickup-message' => [
                'value' => 'To arrange a suitable collection time, please contact James on 0400 130 190.',
                'description' => 'Customer-facing sentence added to order update emails when an order becomes ready for pickup.',
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
            'users.child-accounts-enabled' => [
                'value' => '1',
                'description' => 'Master switch for child account creation and child-account navigation links. Existing child accounts remain usable when disabled.',
                'input_type' => 'boolean',
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
            'moderation.content-filter.exception-words' => [
                'value' => '',
                'description' => 'One word or phrase per line that should be treated as a false positive by Blasp and allowed through the profanity filter. Matching checks both the captured text and Blasp’s resolved word.',
            ],
            'moderation.content-filter.minimum-severity' => [
                'value' => 'mild',
                'description' => 'Lowest Blasp severity that should be blocked. Mild matches the current “block all detected profanity” behaviour.',
            ],
            'moderation.content-filter.profanity-mask-character' => [
                'value' => '*',
                'description' => 'Single character used when profane Minecraft messages can be masked instead of fully hidden.',
            ],
            'moderation.content-filter.blocked-message-placeholder' => [
                'value' => '[Message blocked by moderation filter]',
                'description' => 'Placeholder shown on Minecraft messaging views when a blocked message has no filtered version available.',
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
                'description' => 'Outbound webhook URL used to sync STEMCraft whitelist and punishment changes to the server plugin. Example: http://play.example.com:8125/stemcraft/webhook. Prefer a private/internal HTTP address when Laravel can reach the plugin directly on the same host or network.',
            ],
            'minecraft.webhook-secret' => [
                'value' => '',
                'description' => 'Shared secret used to sign outbound STEMCraft sync requests and verify inbound server webhook calls. Use a long random value, for example from `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.',
            ],
            'minecraft.message-failure-notification-delay-minutes' => [
                'value' => '20',
                'description' => 'Quiet period in minutes before blocked Minecraft messages are grouped into an admin email alert.',
                'input_type' => 'number',
            ],
            'minecraft.public-status-arena-groups' => [
                'value' => 'bridge, parkour, bedwars',
                'description' => 'Comma-separated STEMCraft public-status world group prefixes that should be labelled as arenas instead of worlds. Prefix matching normalizes spaces, hyphens, and underscores.',
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

    public static function booleanValue(string $name, bool $default = false): bool
    {
        $fallback = $default ? '1' : '0';
        $raw = trim((string) (static::value($name, static::defaultValue($name) ?? $fallback) ?? $fallback));

        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
    }
}
