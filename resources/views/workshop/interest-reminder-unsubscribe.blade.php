<x-layout>
    <main class="mx-auto max-w-2xl px-4 py-12 sm:py-16">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @if ($unsubscribed)
                <h1 class="text-2xl font-semibold text-slate-900">Reminders stopped</h1>
                <p class="mt-3 text-slate-600">
                    We’ll no longer send you reminder emails about <strong>{{ $workshop->title }}</strong>.
                    Your interest in the workshop is still recorded.
                </p>
                <a href="{{ route('workshop.show', $workshop) }}" class="mt-6 inline-flex text-sm font-medium text-sky-700 underline">
                    View workshop details
                </a>
            @else
                <h1 class="text-2xl font-semibold text-slate-900">Stop workshop reminders?</h1>
                <p class="mt-3 text-slate-600">
                    We’ll stop sending reminder emails about <strong>{{ $workshop->title }}</strong>.
                    Your interest in the workshop will remain recorded.
                </p>
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ $actionUrl }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
                            Stop these reminders
                        </button>
                    </form>
                    <a href="{{ route('workshop.show', $workshop) }}" class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">
                        Keep reminders
                    </a>
                </div>
            @endif
        </section>
    </main>
</x-layout>
