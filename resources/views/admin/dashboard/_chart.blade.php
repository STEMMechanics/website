@php
    $chartValues = collect($chart['series'])->flatMap(fn ($series) => $series['values']);
    $chartMin = min(0, (float) $chartValues->min());
    $chartMax = max(0, (float) $chartValues->max());
    $chartRange = max(1, $chartMax - $chartMin);
    $plotLeft = 42;
    $plotRight = 684;
    $plotTop = 15;
    $plotBottom = 190;
    $plotWidth = $plotRight - $plotLeft;
    $plotHeight = $plotBottom - $plotTop;
    $pointCount = count($chart['labels']);
    $zeroY = $plotTop + (($chartMax / $chartRange) * $plotHeight);
    $tickEvery = max(1, (int) ceil($pointCount / 8));
    $strokeColors = ['sky' => '#0284c7', 'violet' => '#7c3aed', 'emerald' => '#059669', 'amber' => '#d97706', 'rose' => '#e11d48'];
    $barSeriesIndexes = collect($chart['series'])->keys()->filter(fn ($index) => ($chart['series'][$index]['type'] ?? 'line') === 'bar')->values();
    $barGroupWidth = min(42, ($plotWidth / max(1, $pointCount)) * 0.72);
    $barWidth = $barGroupWidth / max(1, $barSeriesIndexes->count());
@endphp

<div class="mt-5 border-t border-gray-100 pt-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">{{ $chart['title'] }}</h3>
            <p class="mt-1 text-xs text-gray-500">{{ $chart['description'] }}</p>
        </div>
        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium text-gray-600">
            @foreach($chart['series'] as $series)
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $strokeColors[$series['color']] }}"></span>
                    {{ $series['label'] }}
                </span>
            @endforeach
        </div>
    </div>
    <div class="mt-3 overflow-x-auto" role="img" aria-label="{{ $chart['title'] }} trend graph">
        <svg viewBox="0 0 700 225" class="h-auto min-w-[36rem] w-full" aria-hidden="true">
            @foreach([0, 0.5, 1] as $gridPosition)
                @php
                    $gridY = $plotTop + ($gridPosition * $plotHeight);
                    $gridValue = $chartMax - ($gridPosition * $chartRange);
                @endphp
                <line x1="{{ $plotLeft }}" y1="{{ $gridY }}" x2="{{ $plotRight }}" y2="{{ $gridY }}" stroke="#e5e7eb" stroke-width="1" />
                <text x="{{ $plotLeft - 7 }}" y="{{ $gridY + 3 }}" text-anchor="end" fill="#6b7280" font-size="9">
                    {{ $chart['valuePrefix'] }}{{ number_format($gridValue, abs($gridValue) < 10 && $chart['valuePrefix'] === '$' ? 2 : 0) }}
                </text>
            @endforeach
            @if($chartMin < 0)
                <line x1="{{ $plotLeft }}" y1="{{ $zeroY }}" x2="{{ $plotRight }}" y2="{{ $zeroY }}" stroke="#9ca3af" stroke-width="1.5" />
            @endif
            @foreach($chart['series'] as $seriesIndex => $series)
                @php
                    $points = collect($series['values'])->map(function ($value, $index) use ($pointCount, $plotLeft, $plotWidth, $plotTop, $plotHeight, $chartMax, $chartRange) {
                        $x = $pointCount > 1 ? $plotLeft + (($index / ($pointCount - 1)) * $plotWidth) : $plotLeft + ($plotWidth / 2);
                        $y = $plotTop + ((($chartMax - (float) $value) / $chartRange) * $plotHeight);
                        return ['x' => round($x, 2), 'y' => round($y, 2), 'value' => $value];
                    });
                @endphp
                @if(($series['type'] ?? 'line') === 'bar')
                    @php $barIndex = (int) $barSeriesIndexes->search($seriesIndex, strict: true); @endphp
                    @foreach($points as $point)
                        @php
                            $barX = $point['x'] - ($barGroupWidth / 2) + ($barIndex * $barWidth) + 1;
                            $barY = min($point['y'], $zeroY);
                            $barHeight = max(0.5, abs($zeroY - $point['y']));
                        @endphp
                        <rect x="{{ round($barX, 2) }}" y="{{ round($barY, 2) }}" width="{{ round(max(1, $barWidth - 2), 2) }}" height="{{ round($barHeight, 2) }}" rx="2" fill="{{ $strokeColors[$series['color']] }}" opacity="0.82">
                            <title>{{ $series['label'] }}: {{ $chart['valuePrefix'] }}{{ number_format((float) $point['value'], $chart['valuePrefix'] === '$' ? 2 : 0) }}</title>
                        </rect>
                    @endforeach
                @else
                    <polyline points="{{ $points->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ') }}" fill="none" stroke="{{ $strokeColors[$series['color']] }}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    @foreach($points as $point)
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" fill="white" stroke="{{ $strokeColors[$series['color']] }}" stroke-width="2">
                            <title>{{ $series['label'] }}: {{ $chart['valuePrefix'] }}{{ number_format((float) $point['value'], $chart['valuePrefix'] === '$' ? 2 : 0) }}</title>
                        </circle>
                    @endforeach
                @endif
            @endforeach
            @foreach($chart['labels'] as $index => $label)
                @if($index % $tickEvery === 0 || $index === $pointCount - 1)
                    @php $labelX = $pointCount > 1 ? $plotLeft + (($index / ($pointCount - 1)) * $plotWidth) : $plotLeft + ($plotWidth / 2); @endphp
                    <text x="{{ $labelX }}" y="211" text-anchor="middle" fill="#6b7280" font-size="9">{{ $label }}</text>
                @endif
            @endforeach
        </svg>
    </div>
</div>
