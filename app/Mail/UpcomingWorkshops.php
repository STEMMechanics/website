<?php

namespace App\Mail;

use App\Models\NewsletterStoreTheme;
use App\Models\Workshop;
use App\Services\NewsletterProductSelectionService;
use App\Traits\HasUnsubscribeLink;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UpcomingWorkshops extends Mailable
{
    use HasUnsubscribeLink, Queueable, SerializesModels;

    public $subject;

    public $email;

    public $heroHeader;

    public $heroCta;

    public $heroButtonLabel;

    public $heroSubject;

    public $onlineWorkshops;

    public $workshops;

    public $storePromotion;

    public $contentOrder;

    /** @param array<string, mixed>|null $storeSelection */
    public function __construct($email, $subject = 'Upcoming Workshops 🌟', ?array $storeSelection = null)
    {
        $this->subject = $subject;
        $this->email = $email;
        $this->heroButtonLabel = trim((string) config('newsletter.upcoming_workshops.button_label', 'View All Workshops')) ?: 'View All Workshops';
        $upcomingWorkshops = $this->getUpcomingWorkshopSelection();
        $this->workshops = $upcomingWorkshops->whereNotNull('location_id')->values();
        $this->onlineWorkshops = $upcomingWorkshops->whereNull('location_id')->values();
        $this->storePromotion = $storeSelection ?? app(NewsletterProductSelectionService::class)->selection();
        $this->contentOrder = $this->selectContentOrder();
        [$this->heroHeader, $this->heroCta, $this->heroSubject] = $this->selectHeroCopy($this->contentOrder);
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function selectHeroCopy(string $focus): array
    {
        $copyConfig = $focus === 'store' ? $this->storeHeroMessages() : config('newsletter.upcoming_workshops.hero_messages', []);
        $copy = collect($copyConfig)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item): array {
                return [
                    'header' => trim((string) ($item['header'] ?? '')),
                    'cta' => trim((string) ($item['cta'] ?? '')),
                    'subject' => trim((string) ($item['subject'] ?? '')),
                ];
            })
            ->filter(fn (array $item): bool => $item['header'] !== '' && $item['cta'] !== '')
            ->values();

        if ($copy->isEmpty()) {
            if ($focus === 'store') {
                return ['Fresh STEM store picks', 'Discover kits, materials and parts for your next project.', 'Fresh STEM store picks'];
            }

            return [
                'Fresh workshops are ready to book.',
                'Pick your next session, lock in your place, and keep the momentum going with something hands-on.',
                'Upcoming Workshops 🌟',
            ];
        }

        $selected = Arr::random($copy->all());

        return [
            (string) $selected['header'],
            (string) $selected['cta'],
            (string) ($selected['subject'] ?? 'Upcoming Workshops 🌟'),
        ];
    }

    /** @return array<int, array<string, string>> */
    private function storeHeroMessages(): array
    {
        $firstSection = collect($this->storePromotion['sections'] ?? [])->first();
        $themeId = is_array($firstSection) ? (int) ($firstSection['theme_id'] ?? 0) : 0;
        $matchType = $themeId > 0 ? NewsletterStoreTheme::query()->whereKey($themeId)->value('match_type') : null;
        $matchedMessages = is_string($matchType)
            ? config('newsletter.store.hero_messages_by_match.'.$matchType, [])
            : [];

        return is_array($matchedMessages) && $matchedMessages !== []
            ? $matchedMessages
            : config('newsletter.store.hero_messages', []);
    }

    private function selectContentOrder(): string
    {
        $hasWorkshops = $this->workshops->isNotEmpty() || $this->onlineWorkshops->isNotEmpty();
        $hasStore = collect($this->storePromotion['sections'] ?? [])->isNotEmpty();
        if (! $hasWorkshops) {
            return 'store';
        }
        if (! $hasStore) {
            return 'workshops';
        }

        $configured = config('newsletter.content_order');

        return in_array($configured, ['workshops', 'store'], true) ? $configured : Arr::random(['workshops', 'store']);
    }

    private function baseUpcomingWorkshopsQuery(): Builder
    {
        $startDate = Carbon::now()->addHours(6);
        $endDate = Carbon::now()->addDays(42);

        return Workshop::query()
            ->with('location')
            ->publiclyVisible()
            ->where(function ($builder) {
                $builder->whereNull('workshops.is_private')
                    ->orWhere('workshops.is_private', false);
            })
            ->where('workshops.status', '!=', 'private')
            ->whereIn('workshops.status', ['open', 'scheduled'])
            ->whereBetween('workshops.starts_at', [$startDate, $endDate]);
    }

    private function getUpcomingWorkshopSelection(): Collection
    {
        return $this->baseUpcomingWorkshopsQuery()
            ->orderBy('workshops.starts_at')
            ->limit(6)
            ->get()
            ->values();
    }

    public function build()
    {
        if ($this->workshops->isEmpty() && $this->onlineWorkshops->isEmpty() && collect($this->storePromotion['sections'] ?? [])->isEmpty()) {
            return false;
        }

        return $this
            ->subject($this->heroSubject ?: $this->subject)
            ->markdown('emails.upcoming-workshops')
            ->with([
                'email' => $this->email,
                'hideHeader' => true,
                'heroButtonLabel' => $this->heroButtonLabel,
                'heroCta' => $this->heroCta,
                'heroHeader' => $this->heroHeader,
                'heroSubject' => $this->heroSubject,
                'onlineWorkshops' => $this->onlineWorkshops,
                'workshops' => $this->workshops,
                'storePromotion' => $this->storePromotion,
                'contentOrder' => $this->contentOrder,
                'unsubscribeLink' => $this->unsubscribeLink,
            ]);
    }
}
