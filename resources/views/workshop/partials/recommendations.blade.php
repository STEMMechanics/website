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
        x-init="fetch('{{ route('workshop.recommendation.impression') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }, body: JSON.stringify({ source_workshop_id: '{{ $sourceWorkshop->id }}', workshop_ids: '{{ $workshops->pluck('id')->implode(',') }}'.split(','), placement: '{{ $placement }}' }) }).catch(() => {})"
    >
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $title }}</h2>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            @foreach($workshops as $recommendedWorkshop)
                <x-panel-workshop
                    :workshop="$recommendedWorkshop"
                    :href="route('workshop.recommendation.click', ['source' => $sourceWorkshop, 'workshop' => $recommendedWorkshop, 'placement' => $placement])"
                />
            @endforeach
        </div>
    </section>
@endif
