<x-layout>
    <x-mast title="Product allocation profiles" backRoute="admin.cost-centre.allocations" backTitle="Allocation plans" />
    <x-container class="py-5 sm:py-8 space-y-5">
        <x-finance.panel title="Profiles">
            <div class="flex flex-wrap gap-3">
                @foreach($profiles as $profile)<x-ui.button color="outline" :href="route('admin.product-allocation.profiles', ['edit' => $profile->id])">{{ $profile->name }}</x-ui.button>@endforeach
                @if($editing)<x-ui.button color="outline" :href="route('admin.product-allocation.profiles')">New profile</x-ui.button>@endif
            </div>
        </x-finance.panel>
        <x-finance.panel :title="$editing ? 'Edit profile' : 'Create profile'">
            <form method="POST" action="{{ route('admin.product-allocation.profile.save') }}">
                @csrf
                <input type="hidden" name="id" value="{{ $editing?->id }}">
                <x-ui.input name="name" id="name" label="Profile name" :value="$editing?->name ?? ''" required />
                <x-finance.product-allocation-fields :categories="$categories" :rules="$editing ? json_decode($editing->rules, true) : []" />
                <x-finance.save>Save profile</x-finance.save>
            </form>
        </x-finance.panel>
    </x-container>
</x-layout>
