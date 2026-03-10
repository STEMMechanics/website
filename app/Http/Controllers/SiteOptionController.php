<?php

namespace App\Http\Controllers;

use App\Models\SiteOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteOptionController extends Controller
{
    public function index(Request $request): View
    {
        SiteOption::ensureDefaultOptionsExist();

        $query = SiteOption::query();
        $query->where('name', 'not like', 'moderation.%');
        $query->where('name', 'not like', 'minecraft.rcon-%');
        $query->where('name', 'not like', 'minecraft.management-%');

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->value();
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('value', 'like', '%'.$search.'%');
            });
        }

        return view('admin.site-option.index', [
            'siteOptions' => $query->orderBy('name')->paginate(30)->onEachSide(1),
        ]);
    }

    public function create(): View
    {
        return view('admin.site-option.edit');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $this->validateRequest($request);

        $siteOption = SiteOption::query()->create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'option' => $this->siteOptionPayload($siteOption),
            ]);
        }

        session()->flash('message', 'Site option has been created');
        session()->flash('message-title', 'Site option created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.site_option.index');
    }

    public function edit(SiteOption $siteOption): View
    {
        return view('admin.site-option.edit', [
            'siteOption' => $siteOption,
        ]);
    }

    public function update(Request $request, SiteOption $siteOption): RedirectResponse|JsonResponse
    {
        $validated = $this->validateValueRequest($request, (string) $siteOption->name);

        $siteOption->value = $validated['value'] ?? null;
        $siteOption->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'option' => $this->siteOptionPayload($siteOption),
            ]);
        }

        session()->flash('message', 'Site option has been updated');
        session()->flash('message-title', 'Site option updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function resetDefault(SiteOption $siteOption): RedirectResponse|JsonResponse
    {
        if (! SiteOption::hasDefault((string) $siteOption->name)) {
            abort(404);
        }

        $siteOption = SiteOption::resetToDefault((string) $siteOption->name);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'option' => $siteOption ? $this->siteOptionPayload($siteOption) : null,
            ]);
        }

        session()->flash('message', 'Site option has been reset to its default value');
        session()->flash('message-title', 'Site option reset');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function resetAllDefaults(): RedirectResponse|JsonResponse
    {
        SiteOption::resetAllToDefaults();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Default site options were restored.',
            ]);
        }

        session()->flash('message', 'Default site options have been reset and any missing options were created');
        session()->flash('message-title', 'Defaults restored');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.site_option.index');
    }

    public function generateSecret(SiteOption $siteOption): RedirectResponse|JsonResponse
    {
        abort_unless((string) $siteOption->name === 'minecraft.webhook-secret', 404);

        $siteOption->value = bin2hex(random_bytes(32));
        $siteOption->save();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'option' => $this->siteOptionPayload($siteOption),
            ]);
        }

        session()->flash('message', 'A new STEMCraft webhook secret has been generated.');
        session()->flash('message-title', 'Secret regenerated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    private function validateRequest(Request $request, ?SiteOption $siteOption = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:190',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('site_options')->ignore($siteOption?->id),
            ],
            'value' => ['nullable', 'string'],
        ], [
            'name.regex' => 'Name may only contain lowercase letters, numbers, dots, hyphens, and underscores.',
        ]);
    }

    private function validateValueRequest(Request $request, string $optionName): array
    {
        $rules = [
            'value' => ['nullable', 'string'],
        ];

        if ($optionName === 'tickets.hold-minutes') {
            $rules['value'] = ['required', 'integer', 'min:1', 'max:240'];
        }

        if ($optionName === 'store.shipping.max-satchel-weight-grams' || $optionName === 'shop.shipping.max-satchel-weight-grams') {
            $rules['value'] = ['required', 'integer', 'min:0', 'max:50000'];
        }

        if ($optionName === 'store.shipping.boxed-shipping-amount' || $optionName === 'shop.shipping.boxed-shipping-amount') {
            $rules['value'] = ['nullable', 'numeric', 'min:0', 'max:9999.99'];
        }

        if (in_array($optionName, [
            'moderation.content-filter.min-all-caps-letters',
            'moderation.content-filter.max-repeated-character-run',
            'moderation.content-filter.max-repeated-word-run',
        ], true)) {
            $rules['value'] = ['required', 'integer', 'min:1', 'max:100'];
        }

        if (in_array($optionName, [
            'moderation.content-filter.enabled',
            'moderation.content-filter.block-all-caps',
        ], true)) {
            $rules['value'] = ['required', Rule::in(['0', '1'])];
        }

        return $request->validate($rules);
    }

    private function siteOptionPayload(SiteOption $siteOption): array
    {
        return [
            'id' => (int) $siteOption->id,
            'name' => (string) $siteOption->name,
            'value' => (string) ($siteOption->value ?? ''),
            'has_default' => SiteOption::hasDefault((string) $siteOption->name),
            'input_type' => SiteOption::inputType((string) $siteOption->name),
        ];
    }
}
