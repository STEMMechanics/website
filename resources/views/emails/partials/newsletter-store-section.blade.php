@if($storeSections->isNotEmpty())
@foreach($storeSections as $section)
@unless(($hideFirstStoreHeading ?? false) && $loop->first)
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:780px; margin:38px auto 16px auto;">
    <tr><td style="padding:0 0 14px 0; text-align:left;">
        <div style="font-size:30px; line-height:1.1; font-weight:900; color:#0f172a; letter-spacing:-0.03em;">{{ $section['title'] }}</div>
        @if(filled($section['intro'] ?? null))<div style="max-width:680px; margin:10px 0 0 0; color:#64748b; font-size:15px; line-height:1.6;">{{ $section['intro'] }}</div>@endif
    </td></tr>
</table>
@endunless

@foreach(collect($section['products'] ?? []) as $product)
@include('emails.partials.newsletter-product-card', ['product' => $product])
@endforeach
@endforeach

<p class="tall center" style="margin-top:26px;">
    <a href="{{ route('shop.index') }}" target="_blank" rel="noopener" class="newsletter-hero-cta" style="display:inline-block; background:#16a34a; color:#ffffff; text-decoration:none; font-size:18px; font-weight:800; padding:18px 34px; border-radius:24px;">Browse the Store</a>
</p>
@endif
