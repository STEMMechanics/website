@php
    $imageUrl = url($product->primaryImageUrl());
    $productUrl = route('shop.product.show', $product);
    $summary = trim((string) ($product->short_description ?: $product->subtitle));
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="newsletter-workshop-card__table" style="max-width:780px; margin:0 auto 24px auto; background:#ffffff; border:1px solid #e2e8f0; border-left:8px solid #16a34a; border-radius:8px; overflow:hidden;">
<tr>
<td style="padding:18px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tr style="vertical-align:top;">
<td class="newsletter-workshop-card__media-cell mobile-hide" width="220" valign="stretch" style="padding:0 16px 0 0; vertical-align:top;">
    <a href="{{ $productUrl }}" target="_blank" rel="noopener"><img src="{{ $imageUrl }}" alt="{{ $product->title }}" width="220" height="220" class="newsletter-workshop-card__image" style="display:block; width:220px; height:220px; object-fit:cover; border-radius:14px;"></a>
</td>
<td valign="top" class="newsletter-workshop-card__content-cell" style="padding:0;">
    <table role="presentation" width="100%" height="220" cellspacing="0" cellpadding="0" style="height:220px; margin:0;">
        <tr>
            <td valign="top" style="padding:0;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0;">
                    <tr><td style="padding:0 0 10px 0; font-size:20px; line-height:1.22; font-weight:800; color:#0f172a;"><a href="{{ $productUrl }}" target="_blank" rel="noopener" style="color:#0f172a; text-decoration:none;">{{ $product->title }}</a></td></tr>
                    @if($summary !== '')<tr><td style="padding:0 0 10px 0; color:#334155; font-size:14px; line-height:1.5;">{{ \Illuminate\Support\Str::limit($summary, 180) }}</td></tr>@endif
                </table>
            </td>
        </tr>
        <tr>
            <td valign="bottom" style="padding:0;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0; border-top:1px solid #e2e8f0;">
                    <tr>
                        <td valign="middle" style="padding:14px 0 0 0; width:60%;"><span style="font-size:20px; line-height:1; font-weight:900; color:#0f172a; letter-spacing:-0.04em;">{{ $product->priceRangeLabel() }}</span></td>
                        <td valign="middle" align="right" style="padding:14px 0 0 0; width:40%;"><a href="{{ $productUrl }}" target="_blank" rel="noopener" style="display:inline-block; padding:11px 16px; border-radius:10px; background:#f8fafc; border:1px solid #16a34a; color:#16a34a; font-size:14px; font-weight:800; text-decoration:none; text-align:center; white-space:nowrap;">View product</a></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</td>
</tr>
</table>
</td>
</tr>
</table>
