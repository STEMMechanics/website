{!! strip_tags($header ?? '') !!}

@php
$slot = str_replace(['    ', "\t"], '', $slot);
$slot = str_replace('</p>', "\r\n", $slot);
$slot = strip_tags($slot);
@endphp
{!! $slot !!}

@isset($subcopy)
@php
    $subcopy = str_replace(['    ', "\t"], '', $subcopy);
    $subcopy = str_replace("</h4>\n", " - ", $subcopy);
    $subcopy = str_replace(['<br>', '<br />', '</p>'], "\r\n", $subcopy);
    $subcopy = strip_tags($subcopy);
@endphp
{!! $subcopy !!}
@endisset

------
{!! strip_tags($footer ?? '') !!}
