<?php

namespace App\Models;

use App\Services\ExternalBackupService;
use App\Support\StemcraftFaqs;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
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
            'media.upload.non-admin-max-bytes' => [
                'value' => (string) (25 * 1024 * 1024),
                'description' => 'Maximum upload size in bytes for non-admin media uploads. Admin accounts are not limited by this site option.',
                'input_type' => 'number',
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
                'value' => json_encode(ExternalBackupService::defaultFileSources(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]',
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
                'value' => 'If you have any questions about this {document}, please feel free to contact us.',
                'description' => 'Footer questions text for invoice, quote, and tax adjustment PDFs. Use {document} to insert the current document type.',
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
            'store.shipping.processing-pause-until' => [
                'value' => '',
                'description' => 'Optional date that pauses shipping processing while the store is away. Orders can still be placed and will be processed from this date onward.',
                'input_type' => 'date',
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
            'workshops.school-holidays' => [
                'value' => '',
                'description' => 'School holiday dates shaded on workshop calendars. Enter one date or date range per line, for example 2026-06-27 to 2026-07-12.',
            ],
            'workshops.school-holidays-label' => [
                'value' => 'School holidays',
                'description' => 'Label shown in the workshop calendar key for shaded school holiday dates.',
            ],
            'stemcraft.server-status.enabled' => [
                'value' => '0',
                'description' => 'Enable the read-only STEMCraft server status card and internal status endpoint.',
                'input_type' => 'boolean',
            ],
            'stemcraft.server-status.endpoint-url' => [
                'value' => '',
                'description' => 'Server-side endpoint used to fetch basic STEMCraft server status JSON.',
            ],
            'stemcraft.server-status.api-key' => [
                'value' => '',
                'description' => 'Secret API key sent server-side when requesting STEMCraft server status. This value is masked in the admin interface.',
                'input_type' => 'secret',
            ],
            'stemcraft.server-status.server-address' => [
                'value' => 'play.stemcraft.com.au',
                'description' => 'Public server address shown to STEMCraft participants.',
            ],
            'stemcraft.server-status.cache-seconds' => [
                'value' => '60',
                'description' => 'Number of seconds to cache a successful STEMCraft server status response.',
                'input_type' => 'number',
            ],
            'stemcraft.server-status.timeout-seconds' => [
                'value' => '3',
                'description' => 'Number of seconds before the STEMCraft server status request times out.',
                'input_type' => 'number',
            ],
            'stemcraft.server-status.maintenance-message' => [
                'value' => '',
                'description' => 'Optional public maintenance message shown on the STEMCraft server status card.',
            ],
            'stemcraft.monthly-challenge.title' => [
                'value' => 'Build your dream treehouse',
                'description' => 'Heading shown in the monthly STEMCraft challenge section.',
            ],
            'stemcraft.monthly-challenge.description' => [
                'value' => 'Create a treehouse with at least two levels and use redstone to add one moving or interactive feature.',
                'description' => 'Main copy shown in the monthly STEMCraft challenge section. Supports basic Markdown.',
            ],
            'stemcraft.monthly-challenge.prompt' => [
                'value' => 'Think about access, storage, lighting and what would make your treehouse unique.',
                'description' => 'Short prompt shown in the monthly STEMCraft challenge callout. Supports basic Markdown.',
            ],
            'stemcraft.monthly-challenge.image' => [
                'value' => '/stemcraft-technical-build.webp',
                'description' => 'Image shown beside the monthly STEMCraft challenge. Select uploaded media or use a public path/URL.',
                'input_type' => 'media',
            ],
            'stemcraft.monthly-challenge.image-alt' => [
                'value' => 'A STEMCraft build showing a creative engineering challenge',
                'description' => 'Accessible alt text for the monthly challenge image.',
            ],
            'stemcraft.community-builds.1.title' => [
                'value' => 'Castle Build',
                'description' => 'Title for the first STEMCraft community build card.',
            ],
            'stemcraft.community-builds.1.description' => [
                'value' => 'A detailed medieval castle designed with towers, bridges and spaces to explore.',
                'description' => 'Description for the first STEMCraft community build card.',
            ],
            'stemcraft.community-builds.1.image' => [
                'value' => '/stemcraft-calm-build.webp',
                'description' => 'Image for the first STEMCraft community build card. Select uploaded media or use a public path/URL.',
                'input_type' => 'media',
            ],
            'stemcraft.community-builds.1.image-alt' => [
                'value' => 'A detailed castle-style build in STEMCraft',
                'description' => 'Accessible alt text for the first STEMCraft community build image.',
            ],
            'stemcraft.community-builds.2.title' => [
                'value' => 'Creative City',
                'description' => 'Title for the second STEMCraft community build card.',
            ],
            'stemcraft.community-builds.2.description' => [
                'value' => 'A growing shared city filled with streets, homes, public spaces and imaginative details.',
                'description' => 'Description for the second STEMCraft community build card.',
            ],
            'stemcraft.community-builds.2.image' => [
                'value' => '/community-minecraft.webp',
                'description' => 'Image for the second STEMCraft community build card. Select uploaded media or use a public path/URL.',
                'input_type' => 'media',
            ],
            'stemcraft.community-builds.2.image-alt' => [
                'value' => 'A creative city build made by STEMCraft participants',
                'description' => 'Accessible alt text for the second STEMCraft community build image.',
            ],
            'stemcraft.community-builds.3.title' => [
                'value' => 'Working Machine',
                'description' => 'Title for the third STEMCraft community build card.',
            ],
            'stemcraft.community-builds.3.description' => [
                'value' => 'A redstone-powered creation that combines engineering, experimentation and problem-solving.',
                'description' => 'Description for the third STEMCraft community build card.',
            ],
            'stemcraft.community-builds.3.image' => [
                'value' => '/stemcraft-workshop-map.webp',
                'description' => 'Image for the third STEMCraft community build card. Select uploaded media or use a public path/URL.',
                'input_type' => 'media',
            ],
            'stemcraft.community-builds.3.image-alt' => [
                'value' => 'A working STEMCraft mechanism and build area',
                'description' => 'Accessible alt text for the third STEMCraft community build image.',
            ],
            StemcraftFaqs::OPTION => [
                'value' => StemcraftFaqs::defaultJson(),
                'description' => 'Ordered STEMCraft FAQ items. Managed through the dedicated STEMCraft Content admin screen.',
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

    public static function isSecret(string $name): bool
    {
        return static::inputType($name) === 'secret';
    }

    public static function encryptSecretValue(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : Crypt::encryptString($value);
    }

    public static function decryptSecretValue(?string $value): string
    {
        $value = (string) ($value ?? '');
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
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

    public static function secretValue(string $name, ?string $default = null): ?string
    {
        $value = static::value($name, $default);

        return static::decryptSecretValue($value);
    }

    public static function valueToHtml(string $name, ?string $default = null): HtmlString
    {
        return new HtmlString(nl2br(e((string) static::value($name, $default))));
    }

    public static function mediaUrl(string $name, ?string $default = null, string $variant = 'md'): string
    {
        $value = trim((string) (static::value($name, static::defaultValue($name) ?? $default) ?? $default ?? ''));
        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        if (str_starts_with($value, '/')) {
            return asset(ltrim($value, '/'));
        }

        $media = Media::query()->find($value);
        if ($media instanceof Media) {
            return $media->url($variant);
        }

        return asset(ltrim($value, '/'));
    }

    public static function booleanValue(string $name, bool $default = false): bool
    {
        $fallback = $default ? '1' : '0';
        $raw = trim((string) (static::value($name, static::defaultValue($name) ?? $fallback) ?? $fallback));

        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
    }

    public static function intValue(string $name, int $default = 0): int
    {
        $raw = trim((string) (static::value($name, static::defaultValue($name) ?? (string) $default) ?? $default));

        return is_numeric($raw) ? (int) $raw : $default;
    }
}
