<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>STEMMechanics - Contact from Website</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&display=swap');
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                font-size: 1.1rem;
                font-family: Nunito, Arial, Helvetica, sans-serif !important;
                color: #000000;
                padding: 2rem;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale
            }

            div.main {
                margin: 0 auto;
                background-color: #ffffff;
                overflow: hidden;
            }

            div.footer {
                margin: 2rem auto;
                max-width: 48rem;
                font-size: 70%;
                text-align: center;
            }

            p {
                margin-bottom: 1rem;
            }

            a, a:visited, a:hover {
                color: #2563EB;
            }

            a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="main">
            <p>{{ $content }}</p>
            <p>From: {{ $name }} - {{ $email }}</p>
        </div>
        <div class="footer">Sent by STEMMechanics &middot; <a href="https://www.stemmechanics.com.au/">Visit our Website</a> &middot; <a href="https://twitter.com/stemmechanics">@stemmechanics</a><br>PO Box 36, Edmonton, QLD 4869, Australia</div>
    </body>
</html>
