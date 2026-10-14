@props(['stickyMobile' => false])
<div {{ $attributes->class(['sm-editor-actions', 'sm-editor-actions-mobile' => $stickyMobile]) }}>{{ $slot }}</div>
