<?php

namespace App\Mail;

use App\Models\NewsletterStoreTheme;
use App\Services\NewsletterProductSelectionService;
use App\Services\NewsletterWorkshopSelectionService;
use App\Traits\HasUnsubscribeLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

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
    public function __construct($email, $subject = 'Upcoming Workshops 🌟', ?array $storeSelection = null, ?\Carbon\CarbonInterface $releaseAt = null)
    {
        $this->subject = $subject;
        $this->email = $email;
        $this->heroButtonLabel = trim((string) config('newsletter.upcoming_workshops.button_label', 'View All Workshops')) ?: 'View All Workshops';
        $this->storePromotion = $storeSelection ?? app(NewsletterProductSelectionService::class)->selection();
        $upcomingWorkshops = app(NewsletterWorkshopSelectionService::class)->selection($this->storePromotion['excluded_workshop_ids'] ?? [], $releaseAt);
        $this->workshops = $upcomingWorkshops->whereNotNull('location_id')->values();
        $this->onlineWorkshops = $upcomingWorkshops->whereNull('location_id')->values();
        $this->contentOrder = in_array($this->storePromotion['content_order'] ?? null, ['store', 'workshops'], true)
            ? $this->storePromotion['content_order']
            : $this->selectContentOrder();
        if (filled($this->storePromotion['subject'] ?? null) && filled($this->storePromotion['hero_header'] ?? null)
            && filled($this->storePromotion['hero_cta'] ?? null) && in_array($this->storePromotion['content_order'] ?? null, ['store', 'workshops'], true)) {
            $this->heroSubject = (string) $this->storePromotion['subject'];
            $this->heroHeader = (string) $this->storePromotion['hero_header'];
            $this->heroCta = (string) $this->storePromotion['hero_cta'];
        } else {
            [$this->heroHeader, $this->heroCta, $this->heroSubject] = $this->selectHeroCopy($this->contentOrder);
            $this->persistPresentation();
        }
    }

    private function persistPresentation(): void
    {
        $draftId = (int) ($this->storePromotion['draft_id'] ?? 0);
        if ($draftId <= 0) {
            return;
        }

        $selector = app(NewsletterProductSelectionService::class);
        $promotion = $selector->draft();
        if ((int) $promotion->getKey() !== $draftId) {
            return;
        }

        $locked = $selector->lockPresentation($promotion, [
            'subject' => $this->heroSubject,
            'hero_header' => $this->heroHeader,
            'hero_cta' => $this->heroCta,
            'content_order' => $this->contentOrder,
        ]);
        $this->heroSubject = (string) $locked->subject;
        $this->heroHeader = (string) $locked->hero_header;
        $this->heroCta = (string) $locked->hero_cta;
        $this->contentOrder = (string) $locked->content_order;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function selectHeroCopy(string $focus): array
    {
        $selected = Arr::random($this->heroCopyOptions($focus));

        return [$selected['header'], $selected['cta'], $selected['subject']];
    }

    /** @return array<int, array{header:string,cta:string,subject:string}> */
    public function heroCopyOptions(string $focus): array
    {
        $messages = $focus === 'store' ? $this->storeHeroMessages() : config('newsletter.upcoming_workshops.hero_messages', []);
        $options = collect($messages)->filter(fn ($item) => is_array($item))
            ->map(fn (array $item): array => [
                'header' => trim((string) ($item['header'] ?? '')),
                'cta' => trim((string) ($item['cta'] ?? '')),
                'subject' => trim((string) ($item['subject'] ?? '')) ?: ($focus === 'store' ? 'Fresh STEM store picks' : 'Upcoming Workshops 🌟'),
            ])->filter(fn (array $item): bool => $item['header'] !== '' && $item['cta'] !== '')->values()->all();

        return $options ?: [$focus === 'store'
            ? ['header' => 'Fresh STEM store picks', 'cta' => 'Discover kits, materials and parts for your next project.', 'subject' => 'Fresh STEM store picks']
            : ['header' => 'Fresh workshops are ready to book.', 'cta' => 'Pick your next session, lock in your place, and keep the momentum going with something hands-on.', 'subject' => 'Upcoming Workshops 🌟']];
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
