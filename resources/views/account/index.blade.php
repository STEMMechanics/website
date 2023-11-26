<x-layout>
    <x-banner heading="Account" />

    <div class="flex flex-wrap items-center justify-center gap-8 border">
        <x-card href="/account/users/me" class="admin-card">
            <i class="fa-regular fa-circle-user mb-4 text-4xl"></i>
            <p class="text-lg">My Details</p>
        </x-card>

        <x-card href="/account/users" class="admin-card">
            <i class="fa-solid fa-users mb-4 text-4xl"></i>
            <p class="text-lg">Users</p>
        </x-card>

        <x-card href="/account/media" class="admin-card">
            <i class="fa-solid fa-photo-film mb-4 text-4xl"></i>
            <p class="text-lg">Media</p>
        </x-card>

        <x-card class="admin-card">
            <i class="fa-regular fa-circle-user mb-4 text-4xl"></i>
            <p class="text-lg">Posts</p>
        </x-card>

        <x-card class="admin-card">
            <i class="fa-regular fa-circle-user mb-4 text-4xl"></i>
            <p class="text-lg">Workshops</p>
        </x-card>

        <x-card class="admin-card">
            <i class="fa-solid fa-file-invoice-dollar mb-4 text-4xl"></i>
            <p class="text-lg">Quotes</p>
        </x-card>

        <x-card class="admin-card">
            <i class="fa-solid fa-file-invoice-dollar mb-4 text-4xl"></i>
            <p class="text-lg">Invoices</p>
        </x-card>
    </div>
</x-layout>
