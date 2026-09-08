@props(['workshop'])
@if($workshop->isCourse())
    <section {{ $attributes->class(['my-5']) }}>
        <h3 class="mb-2 font-semibold">Course sessions</h3>
        <p class="mb-2 text-sm text-gray-600">One ticket covers all {{ count($workshop->effectiveScheduleEntries()) }} sessions · {{ $workshop->workshopDurationLabel() }} total · {{ config('app.timezone') }}</p>
        <ul class="space-y-2 text-sm text-gray-700">
            @foreach($workshop->courseScheduleDisplayLines() as $session)<li>{{ $session }}</li>@endforeach
        </ul>
    </section>
@endif
