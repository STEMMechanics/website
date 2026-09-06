<select class="{{ twMerge('block min-w-0 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-blue-600 disabled:bg-gray-100 disabled:cursor-not-allowed', $attributes->get('class')) }}" {{ $attributes->except('class') }}>
    {{ $slot }}
</select>
