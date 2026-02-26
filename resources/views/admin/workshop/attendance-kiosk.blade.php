<x-layout-kiosk title="Workshop Sign-In">
    <header class="p-4 mx-auto max-w-2xl flex justify-between items-center">
        <img src="{{ asset('logo.svg') }}" alt="STEMMechanics" class="h-10 w-auto">
        <a href="{{ route('admin.workshop.attendance', $workshop) }}" class="inline-block text-sm text-gray-500 hover:text-primary-color">Exit Kiosk</a>
    </header>
    <main class="px-4 pb-8">
        <div class="mx-auto max-w-2xl rounded-lg border border-gray-200 bg-white p-6">
            <div class="mb-4 text-center">
                <div class="text-2xl font-bold">Sign-In Sheet</div>
                <div class="text-sm text-gray-600">{{ $workshop->title }}</div>
                <div class="text-xs text-gray-500">{{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }} · {{ $workshop->getLocationName() }}</div>
            </div>

            <form method="POST" action="{{ route('admin.workshop.attendance.dropin.store', $workshop) }}" class="grid grid-cols-1 gap-3">
                @csrf
                <input type="hidden" name="kiosk" value="1">

                <x-ui.input name="child_name" label="Child Name" required />
                <x-ui.input name="guardian_name" label="Parent/Guardian Name" required />
                <x-ui.input type="email" name="email" label="Parent/Guardian Email" />
                <x-ui.input name="phone" label="Parent/Guardian Phone" />

                <div>
                    <input type="hidden" name="media_consent" value="0">
                    <x-ui.checkbox name="media_consent" value="1" label="Media consent" />
                </div>

                <p class="text-xs border-y border-gray-300 py-4">By signing this page and indicating consent by the Media Consent checkbox, I give permission for myself and or my child to be photographed, filmed, or recorded during this workshop, and for any artworks, animations, recordings, or other creative works produced as part of the workshop to be used. I consent to STEMMechanics reproducing, editing, and sharing these materials for educational, promotional, and reporting purposes, including websites, social media, newsletters, displays, and sharing with stakeholders and funding partners. This consent applies to this workshop only. I release STEMMechanics from any claims relating to this use. Personal information is handled in accordance with the Information Privacy Act 2009.</p>

                <x-ui.button type="submit" class="mt-4">Sign In</x-ui.button>
            </form>
        </div>
    </main>
</x-layout-kiosk>
