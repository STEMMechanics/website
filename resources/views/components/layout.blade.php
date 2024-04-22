<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="max-upload-size" content="{{ \App\Helpers::getMaxUploadSize() }}">

    <title>{{ 'STEMMechanics' . (isset($title) ? ' - ' . $title : '') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,700;1,400;1,700&amp;display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/script.js"></script>

    @livewireStyles
    @vite('resources/js/app.js')
    @vite('resources/css/app.css')
</head>
<body class="{{ $bodyClass ?? '' }} flex flex-col antialiased dark:bg-black dark:text-white/50">
@if(env('APP_NOTICE'))
    <x-noticebar>{{ env('APP_NOTICE') }}</x-noticebar>
@endif
<x-navbar />
<div {{ isset($id) ? 'id='.$id : '' }} class="flex-grow">{{ $slot }}</div>
<x-footer />
@if (session('message'))
    <script>
        SM.alert('{{ session('message-title') }}', '{{ session('message') }}', '{{ session('message-type') }}');
    </script>
@endif
@livewireScripts
</body>
</html>
