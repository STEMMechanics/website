<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\UpcomingWorkshops;
use App\Models\EmailSubscriptions;
use App\Models\NewsletterStoreTheme;
use App\Models\Product;
use App\Models\SentEmail;
use App\Services\NewsletterProductSelectionService;
use App\Services\NewsletterWorkshopSelectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Throwable;

class EmailSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailSubscriptions::query();

        if ($request->has('search') && $request->search !== '') {
            $query->where('email', 'like', '%'.$request->search.'%');
        }

        $subscriptions = $query
            ->orderBy('created_at', 'desc')
            ->tap(fn ($listingQuery) => app(\App\Services\SiteListControls::class)->apply($listingQuery))->paginate(\App\Support\ListPageSize::resolve(20))
            ->onEachSide(1);

        $subscriptionEmails = $subscriptions->getCollection()
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        $latestNewsletterByEmail = collect();
        if ($subscriptionEmails->isNotEmpty()) {
            $latestNewsletterByEmail = SentEmail::query()
                ->where('mailable_class', UpcomingWorkshops::class)
                ->whereIn('recipient', $subscriptionEmails->all())
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(fn (SentEmail $sentEmail) => strtolower(trim((string) $sentEmail->recipient)))
                ->map(fn (Collection $sentEmails) => $sentEmails->first());
        }

        return view('admin.subscription.index', compact('subscriptions', 'latestNewsletterByEmail'));
    }

    public function newsletter()
    {
        $selector = app(NewsletterProductSelectionService::class);
        $releaseAt = app(NewsletterWorkshopSelectionService::class)->nextRelease();
        $currentStoreSelection = $selector->selection();
        new UpcomingWorkshops('', storeSelection: $currentStoreSelection, releaseAt: $releaseAt);
        $currentStoreSelection = $selector->selection();
        $draft = $selector->draft();
        $workshopSelector = app(NewsletterWorkshopSelectionService::class);

        return view('admin.newsletter.index', [
            'newsletterLinkOptions' => Product::query()->active()->orderBy('title')->get()->map(fn ($product) => ['title' => $product->title, 'type' => 'Store item', 'url' => route('shop.product.show', $product)])
                ->concat(\App\Models\Workshop::query()->publiclyVisible()->where(fn ($query) => $query->whereNull('is_private')->orWhere('is_private', false))->whereIn('status', ['open', 'scheduled'])->where('starts_at', '>=', now())->orderBy('starts_at')->get()->map(fn ($workshop) => ['title' => $workshop->title.' · '.$workshop->starts_at->format('j M Y'), 'type' => 'Workshop', 'url' => route('workshop.show', $workshop)]))->values(),
            'storePromotion' => $draft,
            'storeProductsBySection' => collect($draft->sections)->map(fn (array $section) => $selector->availableProducts($section['category_slugs'])),
            'matchingProductCounts' => collect($draft->sections)->map(fn (array $section) => $selector->matchingProductCount($section)),
            'newsletterReleaseAt' => $releaseAt,
            'newsletterWorkshops' => $workshopSelector->selection($draft->excluded_workshop_ids ?? [], $releaseAt),
            'hiddenNewsletterWorkshops' => $workshopSelector->candidates($releaseAt)->whereIn('workshops.id', $draft->excluded_workshop_ids ?? [])->get(),
            'currentStoreSelection' => $currentStoreSelection,
            'storeThemes' => NewsletterStoreTheme::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function updateWorkshops(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workshop_id' => ['required', 'string', 'exists:workshops,id'],
            'action' => ['required', Rule::in(['hide', 'restore'])],
        ]);
        $draft = app(NewsletterProductSelectionService::class)->draft();
        $excluded = collect($draft->excluded_workshop_ids ?? []);
        $excluded = $validated['action'] === 'hide'
            ? $excluded->push($validated['workshop_id'])->unique()
            : $excluded->reject(fn ($id) => $id === $validated['workshop_id']);
        $draft->update(['excluded_workshop_ids' => $excluded->values()->all()]);

        return redirect()->route('admin.newsletter.index')->with([
            'message' => $validated['action'] === 'hide' ? 'Workshop hidden. The next eligible workshop takes its place.' : 'Workshop restored to the newsletter selection.',
            'message-title' => 'Newsletter workshops updated',
            'message-type' => 'success',
        ]);
    }

    public function updateStorePromotion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'hero_header' => ['sometimes', 'required', 'string', 'max:255'],
            'hero_cta' => ['sometimes', 'required', 'string', 'max:500'],
            'content_order' => ['sometimes', 'required', Rule::in(['store', 'workshops'])],
            'personal_note' => ['sometimes', 'array:enabled,body,image_name,format'],
            'personal_note.enabled' => ['sometimes', 'boolean'],
            'personal_note.body' => ['nullable', 'required_if:personal_note.enabled,1', 'string', 'max:12000'],
            'personal_note.format' => ['sometimes', Rule::in(['text', 'html'])],
            'personal_note.image_name' => ['nullable', 'string', Rule::exists('media', 'name')->where(fn ($query) => $query->where('visibility', 'public')->whereNull('password')->where('mime_type', 'like', 'image/%'))],
            'hero_image_name' => ['sometimes', 'nullable', 'string', Rule::exists('media', 'name')->where(fn ($query) => $query->where('visibility', 'public')->whereNull('password')->where('mime_type', 'like', 'image/%'))],
            'sections' => ['required', 'array', 'size:2'],
            'sections.*.key' => ['required', 'string', 'in:kits,extras'],
            'sections.*.title' => ['required', 'string', 'max:120'],
            'sections.*.intro' => ['nullable', 'string', 'max:400'],
            'sections.*.theme' => ['nullable', 'string', 'in:managed,custom,disabled'],
            'sections.*.theme_id' => ['nullable', 'integer', 'exists:newsletter_store_themes,id'],
            'sections.*.category_slugs' => ['required', 'array', 'min:1'],
            'sections.*.category_slugs.*' => ['required', 'string', 'exists:product_categories,slug'],
            'sections.*.product_ids' => ['nullable', 'array', 'max:3'],
            'sections.*.product_ids.*' => ['nullable', 'integer', 'distinct', 'exists:products,id'],
            'sections.*.product_titles' => ['nullable', 'array', 'max:3'],
            'sections.*.product_titles.*' => ['nullable', 'string', 'max:255'],
            'sections.*.locked_product_ids' => ['nullable', 'array', 'max:3'],
            'sections.*.locked_product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'refresh_section' => ['nullable', 'integer', 'min:0', 'max:1'],
            'fill_empty_slots' => ['nullable', 'integer', 'min:0', 'max:1'],
            'refresh_copy' => ['nullable', 'integer', 'min:0', 'max:1'],
            'apply_theme' => ['nullable', 'integer', 'min:0', 'max:1'],
            'refresh_product' => ['nullable', 'regex:/^[01]:[0-2]$/'],
        ]);

        $selector = app(NewsletterProductSelectionService::class);
        $productsByTitle = Product::query()->active()->get(['id', 'title'])->keyBy(fn (Product $product) => mb_strtolower(trim($product->title)));
        foreach ($validated['sections'] as &$section) {
            if (isset($section['product_titles'])) {
                $section['product_ids'] = collect($section['product_titles'])
                    ->map(fn ($title) => $productsByTitle->get(mb_strtolower(trim((string) $title)))?->id)
                    ->filter()
                    ->values()
                    ->all();
            }
            unset($section['product_titles']);
        }
        unset($section);
        $draft = $selector->draft();
        if (array_key_exists('personal_note', $validated)) {
            if (($validated['personal_note']['format'] ?? 'text') === 'html') {
                $validated['personal_note']['body'] = \App\Services\NewsletterNoteContent::html($validated['personal_note']);
            }
            $text = trim(html_entity_decode(strip_tags($validated['personal_note']['body'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (mb_strlen($text) > 4000 || (($validated['personal_note']['enabled'] ?? false) && $text === '')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['personal_note.body' => 'Enter a message of up to 4,000 characters.']);
            }
            $draft->update(['personal_note' => [
                'enabled' => (bool) ($validated['personal_note']['enabled'] ?? false),
                'body' => trim((string) ($validated['personal_note']['body'] ?? '')),
                'format' => $validated['personal_note']['format'] ?? 'text',
                'image_name' => $validated['personal_note']['image_name'] ?? null,
            ]]);
        }
        if (array_key_exists('hero_image_name', $validated)) {
            $draft->update(['hero_image_name' => $validated['hero_image_name']]);
        }
        if (isset($validated['subject'], $validated['hero_header'], $validated['hero_cta'], $validated['content_order'])) {
            $selector->savePresentation($draft, [
                'subject' => $validated['subject'],
                'hero_header' => $validated['hero_header'],
                'hero_cta' => $validated['hero_cta'],
                'content_order' => $validated['content_order'],
            ]);
        }
        $requestedThemeSection = isset($validated['apply_theme']) ? (int) $validated['apply_theme'] : null;
        $requestedTheme = $requestedThemeSection !== null
            ? NewsletterStoreTheme::query()->find((int) ($validated['sections'][$requestedThemeSection]['theme_id'] ?? 0))
            : null;
        $themeMatchFailed = $requestedTheme instanceof NewsletterStoreTheme && ! $selector->themeHasCandidates($requestedTheme);
        if ($themeMatchFailed && $requestedThemeSection !== null) {
            $validated['sections'][$requestedThemeSection] = $draft->sections[$requestedThemeSection];
        }

        $promotion = $selector->saveSections($draft, $validated['sections']);
        $refreshSection = isset($validated['refresh_section']) ? (int) $validated['refresh_section'] : null;
        $fillEmptySlots = isset($validated['fill_empty_slots']) ? (int) $validated['fill_empty_slots'] : null;
        if ($fillEmptySlots !== null) {
            $promotion = $selector->fillEmptySlots($promotion, $fillEmptySlots);
        }
        if ($refreshSection !== null) {
            $selector->refreshSection($promotion, $refreshSection);
        }
        $refreshCopy = isset($validated['refresh_copy']) ? (int) $validated['refresh_copy'] : null;
        if ($refreshCopy !== null) {
            $selector->refreshCopy($promotion, $refreshCopy);
        }
        $applyTheme = ! $themeMatchFailed && isset($validated['apply_theme']) ? (int) $validated['apply_theme'] : null;
        if ($applyTheme !== null) {
            $selector->applyTheme($promotion, $applyTheme, (int) ($validated['sections'][$applyTheme]['theme_id'] ?? 0));
        }
        $refreshProduct = $validated['refresh_product'] ?? null;
        if (is_string($refreshProduct)) {
            [$sectionIndex, $slot] = array_map('intval', explode(':', $refreshProduct));
            $selector->refreshProduct($promotion, $sectionIndex, $slot);
        }

        session()->flash('message', match (true) {
            $themeMatchFailed => 'No available products match the selected theme. The existing section has been kept unchanged.',
            $fillEmptySlots !== null => 'Empty slots filled where other products are available in this section’s categories. Review the heading and introduction for your final picks.',
            $refreshSection !== null => 'Newsletter product suggestions refreshed.',
            $refreshCopy !== null => 'Newsletter heading and introduction refreshed.',
            $applyTheme !== null => 'Newsletter section rebuilt from the selected theme.',
            $refreshProduct !== null => 'Newsletter product suggestion refreshed.',
            default => 'Newsletter store picks saved.',
        });
        session()->flash('message-title', $themeMatchFailed ? 'Theme has no matching products' : 'Newsletter promotion updated');
        session()->flash('message-type', $themeMatchFailed ? 'warning' : 'success');

        return redirect()->route('admin.newsletter.index');
    }

    public function create()
    {
        return view('admin.subscription.edit');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:email_subscriptions,email'],
        ], [
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
        ]);

        EmailSubscriptions::create([
            'email' => strtolower(trim($request->email)),
            'confirmed' => now(),
        ]);

        session()->flash('message', 'Subscription has been created');
        session()->flash('message-title', 'Subscription created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.subscription.index');
    }

    public function edit(EmailSubscriptions $subscription)
    {
        return view('admin.subscription.edit', compact('subscription'));
    }

    public function update(Request $request, EmailSubscriptions $subscription)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('email_subscriptions', 'email')->ignore($subscription->id),
            ],
        ], [
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
        ]);

        $subscription->update([
            'email' => strtolower(trim($request->email)),
            'confirmed' => now(),
        ]);

        session()->flash('message', 'Subscription has been updated');
        session()->flash('message-title', 'Subscription updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.subscription.index');
    }

    public function destroy(EmailSubscriptions $subscription)
    {
        $subscription->delete();

        session()->flash('message', 'Subscription has been deleted');
        session()->flash('message-title', 'Subscription deleted');
        session()->flash('message-type', 'warning');

        return redirect()->route('admin.subscription.index');
    }

    public function sendNow(EmailSubscriptions $subscription): RedirectResponse
    {
        $email = strtolower(trim((string) $subscription->email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session()->flash('message', 'Unable to send newsletter: subscription email is invalid.');
            session()->flash('message-title', 'Newsletter failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        if ($subscription->confirmed === null || trim((string) $subscription->confirmed) === '') {
            session()->flash('message', 'Cannot send newsletter: this subscription is not confirmed.');
            session()->flash('message-title', 'Newsletter not sent');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        try {
            $this->queueNewsletter($email);
        } catch (Throwable $exception) {
            session()->flash('message', 'Unable to queue newsletter: '.$exception->getMessage());
            session()->flash('message-title', 'Newsletter failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        session()->flash('message', 'Newsletter queued for '.$email.'.');
        session()->flash('message-title', 'Newsletter queued');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function sendTestNow(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ], [
            'test_email.required' => __('validation.custom_messages.email_required'),
            'test_email.email' => __('validation.custom_messages.email_invalid'),
        ]);

        $email = strtolower(trim((string) $validated['test_email']));

        try {
            $this->queueNewsletter($email, releaseAt: app(NewsletterWorkshopSelectionService::class)->nextRelease());
        } catch (Throwable $exception) {
            session()->flash('message', 'Unable to queue newsletter: '.$exception->getMessage());
            session()->flash('message-title', 'Newsletter failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        session()->flash('message', 'Test newsletter queued for '.$email.'.');
        session()->flash('message-title', 'Newsletter queued');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function sendAllNow(): RedirectResponse
    {
        $emails = EmailSubscriptions::query()
            ->whereNotNull('confirmed')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn (string $email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            session()->flash('message', 'No confirmed subscriptions with valid email addresses were found.');
            session()->flash('message-title', 'Newsletter not sent');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        $selector = app(NewsletterProductSelectionService::class);
        $storeSelection = $selector->selection();
        new UpcomingWorkshops('', storeSelection: $storeSelection);
        $storeSelection = $selector->selection();

        try {
            foreach ($emails as $email) {
                $this->queueNewsletter($email, $storeSelection);
            }
            $draft = $selector->draft();
            $selector->clearLocks($draft);
            $selector->clearPresentation($draft);
            new UpcomingWorkshops('', storeSelection: $selector->selection());
        } catch (Throwable $exception) {
            session()->flash('message', 'Unable to queue newsletters: '.$exception->getMessage());
            session()->flash('message-title', 'Newsletter failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        session()->flash('message', 'Newsletter queued for '.$emails->count().' confirmed subscriber'.($emails->count() === 1 ? '' : 's').'.');
        session()->flash('message-title', 'Newsletter queued');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    /** @param array<string, mixed>|null $storeSelection */
    private function queueNewsletter(string $email, ?array $storeSelection = null, ?\Carbon\CarbonInterface $releaseAt = null): void
    {
        dispatch(new SendEmail($email, new UpcomingWorkshops($email, storeSelection: $storeSelection, releaseAt: $releaseAt)))->onQueue('mail');
    }
}
