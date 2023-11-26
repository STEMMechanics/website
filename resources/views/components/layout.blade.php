<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="icon" href="images/favicon.ico" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
            integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA=="
            crossorigin="anonymous" referrerpolicy="no-referrer" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js"
            integrity="sha512-AB2vAMVrtmmI+2BwSMqB+y1qGPNJovUOCp4w27S9pvX8yXPQNbBO4kuM952+LlOpng9VeWPb86b5N32bkvXRvQ=="
            crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
        <script src="/scripts/svg-inject.min.js"></script>
        <link rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,700;1,400;1,700&display=swap"
            as="style" onload="this.onload=null;this.rel='stylesheet'">
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <title>STEMMechanics</title>
    </head>

    <body class="flex flex-col bg-gray-200">
        @include('partials.nav')
        <main {{ $attributes->merge(['class' => 'grow']) }}>
            {{ $slot }}
        </main>

        @include('partials.footer')
        <x-flash-message />
        @stack('scripts')
    </body>

</html>
