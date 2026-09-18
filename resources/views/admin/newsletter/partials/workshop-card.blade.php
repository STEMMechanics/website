<article class="newsletter-editable-card relative">
    <div class="absolute right-3 top-3 z-10 flex gap-2">
        <form method="POST" action="{{ route('admin.newsletter.workshops.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="workshop_id" value="{{ $workshop->id }}">
            <x-ui.button type="submit" name="action" :value="$hidden ? 'restore' : 'hide'" variant="plain" class="h-10 w-10 rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm" :aria-label="$hidden ? 'Restore workshop' : 'Hide from newsletter'" :title="$hidden ? 'Restore workshop' : 'Hide from newsletter'">
                <i class="fa-solid {{ $hidden ? 'fa-eye' : 'fa-eye-slash' }}" aria-hidden="true"></i>
            </x-ui.button>
        </form>
        <a href="{{ route('admin.workshop.edit', $workshop) }}" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm" aria-label="Edit workshop" title="Edit workshop"><i class="fa-solid fa-pencil" aria-hidden="true"></i></a>
    </div>
    @include('emails.partials.upcoming-workshop-card', ['accent' => $workshop->getLocationName() === 'Online' ? '#16a34a' : '#2563eb', 'showLocationFooter' => false, 'compact' => false, 'showSummary' => true, 'showImage' => true])
</article>
