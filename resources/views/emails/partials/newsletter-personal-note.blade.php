@if(\App\Services\NewsletterNoteContent::hasText($personalNote))
<table data-newsletter-personal-note role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:1028px; margin:0 auto 28px auto; border:0;">
<tr>
@if(filled($personalNote['image_url'] ?? null))
<td class="newsletter-note-photo" width="220" style="width:220px; padding:0 28px 0 0; vertical-align:top;">
<img src="{{ $personalNote['image_url'] }}" alt="" width="220" height="220" style="display:block; width:220px; height:220px; object-fit:cover; border-radius:14px;">
</td>
@endif
<td class="newsletter-note-text" style="padding:0; vertical-align:top; color:#334155; font-size:16px; line-height:1.7;">
{!! \App\Services\NewsletterNoteContent::html($personalNote, email: true) !!}
</td>
</tr>
</table>
@endif
