<input
                type="checkbox"
                @if($checked) checked @endif
                @if($disabled) disabled @endif
                @if($resolvedId) id="{{ $resolvedId }}" @endif
                @if($name) name="{{ $name }}" @endif
                @if(!$hasBoundValue) value="1" @endif
                @if($isMixed && !$attributes->has('aria-checked')) aria-checked="mixed" @endif
                @if($isMixed) data-indeterminate="true" @endif
                @if($isMixed && !$attributes->has('x-init')) x-init="$el.indeterminate = true" @endif
                class="{{ twMerge(['sm-checkbox-input', $small ? 'sm-checkbox-small' : '', 'shrink-0', 'bg-white','border','border-gray-300','appearance-none','focus:outline-none','focus:ring-0','focus:border-blue-600','peer','focus:ring-indigo-300','disabled:bg-gray-100','disabled:border-gray-200','disabled:cursor-not-allowed'], $sizeClasses, $inputClass) }}"
                {{ $attributes->except('class') }} />
