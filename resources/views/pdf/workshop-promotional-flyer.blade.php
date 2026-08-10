<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workshop Promotional Flyer</title>
    <style>
        @page { margin: 0; padding: 0; size: A4 landscape; }
        @font-face {
            font-family: 'Poppins';
            font-style: normal;
            font-weight: 400;
            src: url('{{ resource_path('fonts/Poppins-Regular.ttf') }}') format('truetype');
        }
        @font-face {
            font-family: 'Poppins';
            font-style: normal;
            font-weight: 700;
            src: url('{{ resource_path('fonts/Poppins-Bold.ttf') }}') format('truetype');
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; color: #0f172a; font-family: 'Poppins', sans-serif; }
        .flyer { position: fixed; top: 0; width: 33%; height: 100%; overflow: hidden; }
        .flyer.cut { border-right: 0.25mm dashed #94a3b8; }
        .brand { width: 100%; text-align: center; margin: 6mm 0 0; }
        .logo { display: inline-block; width: 38mm; max-height: 9mm; }
        .workshops-list { width: 80%; margin: 0 auto; }
        .workshop-slot { padding: 1.25mm 0; }
        .workshop { overflow: hidden; border-radius: 8px; }
        .media-cell { height: 28mm; padding: 0; text-align: center; }
        .workshop-image { display: block; height: auto; width: auto; max-width: 100%; margin: 0 auto; }
        .image-placeholder { height: 28mm; background: #e0f2fe; }
        .placeholder-mark { padding-top: 6mm; color: #0284c7; font-size: 8pt; font-weight: 700; text-align: center; }
        .content-cell { height: 30.5mm; padding: 2mm 3mm; vertical-align: top; }
        .content-layout { width: 100%; height: 26.5mm; border-collapse: collapse; table-layout: fixed; }
        .workshop-header { position: relative; width: 100%; height: 7mm; }
        .title { position: absolute; bottom: 0; font-size: 10pt; font-weight: 700; }
        .location { position: absolute; right: 0; bottom: 0.8mm; color: #2563eb; font-size: 6pt; font-weight: 700; letter-spacing: 0.5pt; text-transform: uppercase; width: 40mm; text-align: right; line-height: 5pt; }
        .accent-1 .location { color: #15803d; }
        .accent-2 .location { color: #b45309; }
        .workshop-location { width: 32%; padding: 0; text-align: right; vertical-align: top; }
        .description { height: 15.5mm; overflow: hidden; padding: 1mm 0 0; color: #475569; font-size: 6.4pt; line-height: 1.1; vertical-align: top; }
        .date { height: 4mm; color: #334155; font-size: 6.5pt; font-weight: 700; line-height: 1.1; margin-bottom: 2mm; }
        .footer { position: fixed; bottom: 6mm; width: 33%; }
        .footer div { margin: 0 auto; background-color: #0284c7; color: #fff; font-size: 8pt; font-weight: 700; text-align: center; border-radius: 8px; width: 80%; padding: 5px 0 8px 0; }
    </style>
</head>
<body>
@php
    $logoPath = public_path('logo.png');
    if (! file_exists($logoPath)) {
        $logoPath = public_path('logo.png');
    }
    $logoData = file_exists($logoPath)
        ? 'data:'.(mime_content_type($logoPath) ?: 'image/png').';base64,'.base64_encode(file_get_contents($logoPath))
        : null;
@endphp
@for($copy = 0; $copy < 3; $copy++)
    <section class="flyer {{ $copy < 2 ? 'cut' : '' }}" style="left: {{ $copy * 99 }}mm;">
        <header class="brand">
            @if($logoData)
                <img class="logo" src="{{ $logoData }}" alt="STEMMechanics">
            @else
                <strong>STEMMechanics</strong>
            @endif
        </header>

        <div class="workshops-list">
            @foreach($flyerWorkshops as $item)
                @php
                    $workshop = $item['workshop'];
                    $price = $workshop->currentTicketPriceAmount();
                    $priceLabel = $price > 0.0001 ? '$'.number_format($price, 2) : 'Free';
                @endphp

                    <div class="workshop-slot">
                        <div class="workshop accent-{{ $loop->index }}">
                            <div class="media-cell">
                                @if($item['image'])
                                    <img class="workshop-image" src="{{ $item['image'] }}" alt="">
                                @else
                                    <div class="image-placeholder"><div class="placeholder-mark">STEM</div></div>
                                @endif
                            </div>
                            <div class="workshop-header">
                                <span class="title">{{ $workshop->title }}</span>
                                <span class="location">{{ $workshop->getLocationName() }}</span>
                            </div>
                            <div class="description">{{ $item['description'] }}</div>
                            <div class="date">
                                {{ $workshop->starts_at?->format('D j M, g:i a') }}
                                @if($workshop->workshopDurationLabel())
                                    &middot; {{ $workshop->workshopDurationLabel() }}
                                @endif
                                &middot; {{ $priceLabel }}
                            </div>

                            {{--                                        <tr>--}}
{{--                                            <td class="date">--}}
{{--                                                {{ $workshop->starts_at?->format('D j M, g:i a') }}--}}
{{--                                                @if($workshop->workshopDurationLabel())--}}
{{--                                                    &middot; {{ $workshop->workshopDurationLabel() }}--}}
{{--                                                @endif--}}
{{--                                                &middot; {{ $priceLabel }}--}}
{{--                                            </td>--}}
{{--                                        </tr>--}}
{{--                                    </table>--}}
{{--                                </td>--}}
{{--                            </tr>--}}
                        </div>
                    </div>
            @endforeach
        </div>
    </section>
    <footer class="footer" style="left: {{ ($copy * 99) }}mm;"><div>{{ $footer }}</div></footer>
@endfor
</body>
</html>
