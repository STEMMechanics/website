<x-layout title="Unsubscribe">
    <main class="mx-auto max-w-2xl px-4 py-12 sm:py-16">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @if ($alreadyUnsubscribed)
                <h1 class="text-2xl font-semibold text-slate-900">You are already unsubscribed</h1>
                <p class="mt-3 text-slate-600">This email address is not subscribed to STEMMechanics updates.</p>
            @else
                <h1 class="text-2xl font-semibold text-slate-900">Unsubscribe from STEMMechanics updates?</h1>
                <p class="mt-3 text-slate-600">Use the button below to stop future workshop and STEMMechanics update emails.</p>
                <form method="POST" action="{{ $actionUrl }}" class="mt-6">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
                        Unsubscribe
                    </button>
                </form>
            @endif
        </section>
    </main>
</x-layout>
