@props([
    'title' => 'Sign-In Sheet',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" src="{{ \Illuminate\Support\Facades\Vite::asset('node_modules/sweetalert2/dist/sweetalert2.all.min.js') }}"></script>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" src="/script.js?v={{ @filemtime(public_path('script.js')) ?: time() }}"></script>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-gray-100 antialiased">
    {{ $slot }}

    <x-ui.flash-notifications />
</body>
</html>
