<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        @vite('resources/css/app.css')

        <title>Laravel</title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.2/cdn.js" integrity="sha512-lNq2c0EZyCnieSFk9jEWqD60SbJY/6MWMaJsEHk1Tq3y8N5c9E9PuNlSq1jhf499bTkTslfexdzcz7zSTk9Qzw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    </head>
    <body class="antialiased">
        @yield('content')
    </body>
</html>
