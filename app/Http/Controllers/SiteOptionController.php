<?php

namespace App\Http\Controllers;

use App\Models\SiteOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteOptionController extends Controller
{
    public function index(Request $request): View
    {
        $query = SiteOption::query();

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

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        SiteOption::query()->create($validated);

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

    public function update(Request $request, SiteOption $siteOption): RedirectResponse
    {
        $validated = $this->validateRequest($request, $siteOption);

        $siteOption->fill($validated);
        $siteOption->save();

        session()->flash('message', 'Site option has been updated');
        session()->flash('message-title', 'Site option updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function destroy(Request $request, SiteOption $siteOption)
    {
        $siteOption->delete();

        session()->flash('message', 'Site option has been deleted');
        session()->flash('message-title', 'Site option deleted');
        session()->flash('message-type', 'danger');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.site_option.index'),
            ]);
        }

        return redirect()->route('admin.site_option.index');
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
}
