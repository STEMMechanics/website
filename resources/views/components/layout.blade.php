@props([
    'id' => null,
    'bodyClass' => '',
    'title' => null,
    'description' => null,
    'canonical' => null,
    'ogImage' => null,
    'noindex' => false,
    'jsonLd' => null,
])

@php
    $siteName = 'STEMMechanics';
    $pageTitle = trim((string) ($title ?? ''));
    $fullTitle = $pageTitle !== '' ? $siteName . ' - ' . $pageTitle : $siteName;

    $defaultDescription = 'Hands-on STEM workshops, creative technology programs, and community learning experiences by STEMMechanics.';
    $metaDescription = trim((string) ($description ?? ''));
    if ($metaDescription === '') {
        $metaDescription = $defaultDescription;
    }

    $canonicalUrl = trim((string) ($canonical ?? url()->current()));
    if ($canonicalUrl === '') {
        $canonicalUrl = url()->current();
    }

    $ogImageUrl = trim((string) ($ogImage ?? asset('home-hero.webp')));
    if ($ogImageUrl === '') {
        $ogImageUrl = asset('home-hero.webp');
    }

    $robots = filter_var($noindex, FILTER_VALIDATE_BOOL) ? 'noindex, nofollow' : 'index, follow';

    $jsonLdBlocks = [];
    if (is_array($jsonLd)) {
        if (array_is_list($jsonLd)) {
            $jsonLdBlocks = $jsonLd;
        } else {
            $jsonLdBlocks = [$jsonLd];
        }
    } elseif (is_string($jsonLd) && trim($jsonLd) !== '') {
        $decoded = json_decode($jsonLd, true);
        if (is_array($decoded)) {
            $jsonLdBlocks = array_is_list($decoded) ? $decoded : [$decoded];
        }
    }

    $organizationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $siteName,
        'url' => url('/'),
        'logo' => asset('logo.svg'),
    ];

    $webPageSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $pageTitle !== '' ? $pageTitle : $siteName,
        'description' => $metaDescription,
        'url' => $canonicalUrl,
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="smid" content="AC9E94587F163AD93174FBF3DFDF9645B886960F2F8DD6D60F81CDB6DCDA3BC3">
    <meta name="max-upload-size" content="{{ \App\Helpers::getMaxUploadSize(auth()->user()) }}">
    <meta name="media-upload-url" content="{{ auth()->check() ? route('media.store') : '' }}">

    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $ogImageUrl }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $fullTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $ogImageUrl }}">

    <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($webPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @foreach($jsonLdBlocks as $schema)
        @if(is_array($schema))
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    @endforeach

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,700;1,400;1,700&amp;display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/script.js?v={{ @filemtime(public_path('script.js')) ?: time() }}"></script>

    @livewireStyles
    @vite('resources/js/app.js')
    @vite('resources/css/app.css')
</head>
<body class="{{ $bodyClass }} flex flex-col antialiased">
@if(trim((string)($appNotice ?? '')) !== '')
    <x-noticebar>{{ $appNotice }}</x-noticebar>
@endif
<x-navbar />
<div {{ $id ? 'id='.$id : '' }} class="grow">{{ $slot }}</div>
<x-footer />
@if (session('message'))
    <script>
        SM.alert('{{ session('message-title') }}', '{{ session('message') }}', '{{ session('message-type') }}');
    </script>
@endif
@stack('scripts')
@livewireScripts
</body>
</html>
