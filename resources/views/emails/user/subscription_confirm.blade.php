<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>STEMMechanics - Subscription</title>
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

            a.brand {
                display: block;
                margin-bottom: 2rem;
                padding: 0 2rem;
            }

            a.brand:hover {
                text-decoration: none;
            }

            h2 {
                margin-bottom: 2rem;
            }

            p {
                margin-bottom: 1rem;
            }

            a.brand img {
                width: 100%;
                max-width: 100%;
                object-fit: contain;
            }

            .code {
                display: block;
                font-size: 200%;
                text-align: center;
                margin-top: 2rem;
                margin-bottom: 2rem;
                letter-spacing: 0.5rem;
            }

            .feedback {
                font-size: 90%;
                text-align: center;
            }

            .border {
                border-top: 1px solid #ddd;
                margin-bottom: 2rem;
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
            <a href="https://www.stemmechanics.com.au/" class="brand">
                <img alt="STEMMechanics Logo" src="{{ $message->embed(public_path('img').'/logo.png') }}">
            </a>
            <h2>Howdy there,</h2>
            <p>At your request, you are now subscribed to our newsletter giving you tips, tricks and letting you know when new workshops are scheduled.</p>
            <p>If this wasn't you, you can unsubscribe by visiting <a href="https://www.stemmechanics.com.au/unsubscribe?email={{ $email }}">stemmechanics.com.au/unsubscribe</a></p>
            <div class="border"></div>
            <p class="feedback">Need help or got feedback? <a href="https://www.stemmechanics.com.au/contact">Contact us</a> or touch base at <a href="https://twitter.com/stemmechanics">@stemmechanics</a>.</p>
        </div>
        <div class="footer">Sent by STEMMechanics &middot; <a href="https://www.stemmechanics.com.au/">Visit our Website</a> &middot; <a href="https://twitter.com/stemmechanics">@stemmechanics</a><br>PO Box 36, Edmonton, QLD 4869, Australia</div>
    </body>
</html>
