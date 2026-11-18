@props([
    'workshops',
    'sourceWorkshop',
    'placement' => 'workshop',
    'title' => 'Other workshops you may like',
])

@if($workshops->isNotEmpty())
    <section
        class="mt-20 pt-4 border-t  border-gray-300 p-4"
        x-data
        x-init="$SM.trackWorkshopRecommendationImpression(@js(route('workshop.recommendation.impression')), @js(csrf_token()), @js($sourceWorkshop->id), @js($workshops->pluck('id')->values()->all()), @js($placement))"
    >
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $title }}</h2>
            </div>
        </div>
        <x-ui.grid class="gap-5 md:grid-cols-3">
            @foreach($workshops as $recommendedWorkshop)
                <x-panel-workshop
                    :workshop="$recommendedWorkshop"
                    :href="route('workshop.recommendation.click', ['source' => $sourceWorkshop, 'workshop' => $recommendedWorkshop, 'placement' => $placement])"
                />
            @endforeach
        </x-ui.grid>
    </section>
@endif
