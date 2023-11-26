@props(['resend'])

<p>
    Enter the verification code you received</p>

<div class="floating-label mb-6 mt-12">
    <input type="text" class="w-full rounded border border-gray-200 p-2" name="code" value="{{ old('code') }}"
        required autofocus />
    <label for="code" class="mx-1 mb-1 inline-block text-sm text-gray-800">Verification code</label>
    @error('code')
        <p class="error">{{ $message }}</p>
    @enderror
</div>

@if (isset($resend))
    <div class="flex items-center justify-between">
        <div>
            <p id="resend-link" class="hidden text-xs text-gray-600">Didn't receive the code? <a href="?resend=1"
                    class="text-blue transition hover:text-blue-dark">
                    Resend Email
                </a>
            </p>
        </div>
        <div>
            <button type="submit" class="rounded bg-green px-8 py-2 text-white transition hover:bg-green-dark">
                Verify Code
            </button>
        </div>
    @else
        <div class="flex items-center justify-end">
            <button type="submit" class="btn-green">
                Verify Code
            </button>
        </div>
@endif

@push('scripts')
    <script type="module">
        window.stemmech.ready(() => {
            const code = stemmech.getQueryParam('code');
            const resend = document.getElementById('resend-link');
            const codeInput = document.getElementsByName('code')[0];

            if (code && codeInput && codeInput.value.trim() === '') {
                codeInput.value = code;
                var form = document.querySelector("form");
                if (form) form.submit();
            } else if (resend) {
                window.setTimeout(() => {
                    resend.classList.remove('hidden');
                }, 30000);
            }
        });
    </script>
@endpush
