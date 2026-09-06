@props(['media'])
@php
    $passwordProtected = (bool) $media->password;
    $label = $passwordProtected ? 'Password protected' : ucfirst($media->visibility ?: 'private');
    $color = $passwordProtected || $media->visibility === 'protected' ? 'warning' : ($media->visibility === 'public' ? 'success' : 'slate');
@endphp
<x-ui.badge :color="$color" :icon="$passwordProtected ? 'fa-solid fa-lock' : null">{{ $label }}</x-ui.badge>
