@php
    $groups = $groups ?? [];
    $majorGroupName = $majorRecognitionLevel?->name ?: 'Major Sponsors';
    $majorSponsors = collect($groups[$majorGroupName] ?? []);
    $otherSponsors = collect($groups)->except($majorGroupName)->flatten(1)->values();
@endphp
<x-layout title="Our sponsors — STEMMechanics">
    <x-mast title="Our sponsors" description="Meet the people and organisations partnering with STEMMechanics." />

    <x-container class="py-7 sm:py-10">
        <div class="mx-auto max-w-5xl">
            <section id="supporters" class="mt-4 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8" aria-labelledby="supporters-heading">
                <div>
                    <h1 id="supporters-heading" class="text-2xl font-semibold text-gray-900">People and organisations behind the work</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">Their partnerships help keep STEM learning accessible, equip our workshops and carry our programs into more communities across North Queensland.</p>
                </div>

                @if($publicSponsors->isNotEmpty())
                    <div class="mt-8 space-y-8">
                        @if($majorSponsors->isNotEmpty())
                            <div>
                                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-gray-500">{{ $majorGroupName }}</h2>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach($majorSponsors as $sponsor)
                                        @php($website = $sponsor->website_url)
                                        @if($website)
                                            <a href="{{ $website }}" target="_blank" rel="noopener noreferrer" class="flex min-h-24 flex-col items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-3 text-center transition hover:border-sky-300 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-color">
                                        @else
                                            <div class="flex min-h-24 flex-col items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-3 text-center">
                                        @endif
                                            @if($sponsor->recognition_logo_path)
                                                <img src="{{ route('sponsor.asset', ['type' => 'sponsor', 'id' => $sponsor->id]) }}" alt="{{ $sponsor->publicLabel() }}" class="max-h-14 max-w-full object-contain" loading="lazy">
                                            @endif
                                            <span class="text-sm font-semibold text-gray-900">{{ $sponsor->publicLabel() }}</span>
                                            <span class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ $majorGroupName }}</span>
                                            @if(filled($sponsor->public_message))
                                                <p class="mt-2 max-w-xs text-xs leading-5 text-gray-600">{{ $sponsor->public_message }}</p>
                                            @endif
                                        @if($website)</a>@else</div>@endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($otherSponsors->isNotEmpty())
                            <div>
                                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-gray-500">Sponsors and supporters</h2>
                                <div class="mt-3 grid grid-cols-2 gap-x-5 gap-y-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                                    @foreach($otherSponsors as $sponsor)
                                        @php($website = $sponsor->website_url)
                                        @if($website)
                                            <a href="{{ $website }}" target="_blank" rel="noopener noreferrer" class="flex flex-col items-center justify-center rounded-lg px-2 py-2 text-center text-sm font-semibold text-gray-800 transition hover:bg-sky-50 hover:text-primary-color focus:outline-none focus:ring-2 focus:ring-primary-color">
                                        @else
                                            <span class="flex flex-col items-center justify-center rounded-lg px-2 py-2 text-center text-sm font-semibold text-gray-800">
                                        @endif
                                            <span>{{ $sponsor->publicLabel() }}</span>
                                            @if(filled($sponsor->public_message))
                                                <span class="mt-1 text-xs font-normal leading-5 text-gray-600">{{ $sponsor->public_message }}</span>
                                            @endif
                                        @if($website)</a>@else</span>@endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="mt-8 rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm leading-6 text-sky-950">Our public sponsor list is being updated. If you support STEMMechanics and would like to be recognised, choose a sponsorship option and opt in during checkout.</p>
                @endif
            </section>

            <section class="mt-6 flex flex-col gap-4 rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between" aria-labelledby="sponsor-call-to-action-heading">
                <div>
                    <h2 id="sponsor-call-to-action-heading" class="font-semibold text-gray-900">Interested in partnering with STEMMechanics?</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-700">Explore sponsorship opportunities for businesses, organisations and individuals.</p>
                </div>
                <x-ui.button class="shrink-0" href="{{ route('sponsor.index') }}">Explore sponsorships</x-ui.button>
            </section>
        </div>
    </x-container>
</x-layout>
