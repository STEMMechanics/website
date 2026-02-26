@props([
    'title' => 'Sign-In Sheet',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/script.js?v={{ @filemtime(public_path('script.js')) ?: time() }}"></script>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-gray-100 antialiased">
    {{ $slot }}

    @if (session('message'))
        <script>
            SM.alert('{{ session('message-title') }}', '{{ session('message') }}', '{{ session('message-type') }}');
        </script>
    @endif
</body>
</html>
