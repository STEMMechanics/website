<?php

namespace App\Support;

use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;

final class UiField
{
    public readonly string $id;

    private readonly ?string $idBinding;

    private readonly bool $generatedBinding;

    public function __construct(
        private readonly ComponentAttributeBag $attributes,
        private readonly bool $hasHelp = false,
        private readonly bool $hasError = false,
    ) {
        $explicitId = trim((string) $attributes->get('id', ''));
        $binding = trim((string) $attributes->get('x-bind:id', $attributes->get(':id', '')));
        $boundName = $attributes->has('x-bind:name') || $attributes->has(':name');
        $this->id = $explicitId ?: 'ui-field-'.Str::uuid();
        $this->generatedBinding = $explicitId === '' && $binding === '' && $boundName;
        $this->idBinding = $binding !== '' ? $binding : ($this->generatedBinding ? "\$id('ui-field')" : null);
    }

    public function rootAttributes(): ComponentAttributeBag
    {
        $attributes = $this->generatedBinding ? ['x-id' => "['ui-field']"] : [];
        $nameBinding = $this->attributes->get('x-bind:name', $this->attributes->get(':name'));
        if ($nameBinding !== null) {
            $attributes['x-bind:data-validation-field'] = $nameBinding;
        }

        return new ComponentAttributeBag($attributes);
    }

    public function labelAttributes(): ComponentAttributeBag
    {
        $attributes = ['for' => $this->id];
        if ($this->idBinding !== null) {
            $attributes['x-bind:for'] = $this->idBinding;
        }

        return new ComponentAttributeBag($attributes);
    }

    public function feedbackAttributes(string $suffix): ComponentAttributeBag
    {
        $attributes = ['id' => $this->id.'-'.$suffix];
        if ($this->idBinding !== null) {
            $attributes['x-bind:id'] = '('.$this->idBinding.") + '-{$suffix}'";
        }

        return new ComponentAttributeBag($attributes);
    }

    public function controlAttributes(): ComponentAttributeBag
    {
        $attributes = $this->attributes->except(['class', 'x-show', 'x-cloak']);
        $defaults = ['id' => $this->id];
        $suffixes = $this->hasHelp ? ['help', 'error'] : ['error'];
        $existingDescription = trim((string) $attributes->get('aria-describedby', ''));
        $defaults['aria-describedby'] = trim($existingDescription.' '.implode(' ', array_map(fn (string $suffix): string => $this->id.'-'.$suffix, $suffixes)));

        if ($this->hasError) {
            $defaults['aria-invalid'] = 'true';
        }
        if ($this->generatedBinding) {
            $defaults['x-bind:id'] = $this->idBinding;
        }
        $existingBinding = $attributes->get('x-bind:aria-describedby', $attributes->get(':aria-describedby'));
        if ($this->idBinding !== null || $existingBinding !== null) {
            $parts = [$existingBinding !== null ? '('.$existingBinding.')' : Js::from($existingDescription)->toHtml()];
            foreach ($suffixes as $suffix) {
                $parts[] = $this->idBinding !== null
                    ? '('.$this->idBinding.") + '-{$suffix}'"
                    : Js::from($this->id.'-'.$suffix)->toHtml();
            }
            $defaults['x-bind:aria-describedby'] = '['.implode(', ', $parts)."].filter(Boolean).join(' ')";
            $attributes = $attributes->except(['aria-describedby', 'x-bind:aria-describedby', ':aria-describedby']);
        } else {
            $attributes = $attributes->except('aria-describedby');
        }

        return $attributes->merge($defaults);
    }
}
