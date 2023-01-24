<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    xmlns="http://www.w3.org/1999/xhtml"
    xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:o="urn:schemas-microsoft-com:office:office"
>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <title>STEMMechanics - Forgot Password</title>
        <link
            rel="noopener"
            target="_blank"
            href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&display=swap"
            rel="stylesheet"
        />
        <!--[if gte mso 9]>
            <xml>
                <o:OfficeDocumentSettings>
                    <o:AllowPNG />
                    <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings>
            </xml>
        <![endif]-->
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&display=swap");
        </style>
    </head>
    <body>
        <table
            cellspacing="0"
            cellpadding="0"
            border="0"
            role="presentation"
            style="
                width: 100%;
                padding: 2rem;
                font-size: 1.1rem;
                color: #000000;
                font-family: Nunito, Arial, Helvetica, sans-serif;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            "
        >
            <tr>
                <td>
                    <a href="https://www.stemmechanics.com.au/">
                        <img
                            alt="STEMMechanics Logo"
                            src="{{ $message->embed(public_path('img').'/logo.png') }}"
                            width="400"
                            height="62"
                        />
                    </a>
                </td>
            </tr>
            <tr>
                <td>            
                    @if (count($usernames) > 2)
                    <h2>Yo {{ $usernames[0] }}, {{ $usernames[1] }}, or is it {{ $usernames[count($usernames)-1] }}?</h2>
                    @elseif (count($usernames) > 1)
                    <h2>Yo {{ $usernames[0] }}, or is it {{ $usernames[1] }}?</h2>
                    @else
                    <h2>Yo {{ $usernames[0] }},</h2>
                    @endif
                </td>
            </tr>
            <tr>
                <td>
                    @if (count($usernames) == 1)
                    Guess what, your username is <strong>{{ $usernames[0] }}</strong>.
                    @else
                    We have the following usernames registered to this email address:
                </td>
            </tr>
            <tr>
                <td>
                    <ul style="padding-top: 2rem; padding-bottom: 2rem;">
                        @foreach($usernames as $username)
                        <li>{{ $username }}</li>
                        @endforeach
                    </ul>
                    @endif
                </td>
            </tr>
            <tr>
                <td
                    align="center"
                    style="
                        font-size: 90%;
                        text-align: center;
                        padding-top: 2rem;
                        padding-bottom: 2rem;
                        border-top: 1px solid #ddd;
                    "
                >
                    Need help or got feedback?
                    <a href="https://www.stemmechanics.com.au/contact"
                        >Contact us</a
                    >
                    or touch base at
                    <a href="https://twitter.com/stemmechanics"
                        >@stemmechanics</a
                    >.
                </td>
            </tr>
            <tr>
                <td
                    align="center"
                    style="
                        font-size: 80%;
                        text-align: center;
                        padding-top: 1rem;
                        padding-bottom: 2rem;
                    "
                >
                    Sent by STEMMechanics &middot;
                    <a href="https://www.stemmechanics.com.au/"
                        >Visit our Website</a
                    >
                    &middot;
                    <a href="https://twitter.com/stemmechanics"
                        >@stemmechanics</a
                    ><br />PO Box 36, Edmonton, QLD 4869, Australia
                </td>
            </tr>
        </table>
    </body>
</html>
