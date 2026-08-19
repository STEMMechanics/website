<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\UpcomingWorkshops;
use App\Models\EmailSubscriptions;
use App\Models\NewsletterStoreTheme;
use App\Models\Product;
use App\Models\SentEmail;
use App\Services\NewsletterProductSelectionService;
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
            ->paginate(20)
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

        $selector = app(NewsletterProductSelectionService::class);

        return view('admin.subscription.index', [
            'subscriptions' => $subscriptions,
            'latestNewsletterByEmail' => $latestNewsletterByEmail,
            'storePromotion' => $selector->draft(),
            'storeProducts' => Product::query()->active()->orderBy('title')->get(['id', 'title', 'sku']),
            'currentStoreSelection' => $selector->selection(),
            'storeThemes' => NewsletterStoreTheme::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function updateStorePromotion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
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
            $refreshSection !== null => 'Newsletter product suggestions refreshed.',
            $refreshCopy !== null => 'Newsletter heading and introduction refreshed.',
            $applyTheme !== null => 'Newsletter section rebuilt from the selected theme.',
            $refreshProduct !== null => 'Newsletter product suggestion refreshed.',
            default => 'Newsletter store picks saved.',
        });
        session()->flash('message-title', $themeMatchFailed ? 'Theme has no matching products' : 'Newsletter promotion updated');
        session()->flash('message-type', $themeMatchFailed ? 'warning' : 'success');

        return redirect()->route('admin.subscription.index');
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
            $this->queueNewsletter($email);
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

        try {
            foreach ($emails as $email) {
                $this->queueNewsletter($email, $storeSelection);
            }
            $selector->clearLocks($selector->draft());
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
    private function queueNewsletter(string $email, ?array $storeSelection = null): void
    {
        dispatch(new SendEmail($email, new UpcomingWorkshops($email, storeSelection: $storeSelection)))->onQueue('mail');
    }
}
