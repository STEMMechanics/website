@props(['workshop'])
@if($workshop->isCourse())
    <section {{ $attributes->class(['my-5']) }}>
        <h3 class="mb-2 font-semibold">Course sessions</h3>
        <ul class="space-y-2 text-sm text-gray-700">
            @foreach($workshop->courseScheduleDisplayLines() as $session)<li>{{ $session }}</li>@endforeach
        </ul>
    </section>
@endif
