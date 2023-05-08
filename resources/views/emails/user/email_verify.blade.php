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
                            src="{{ $message->embed(public_path('assets').'/logo.webp') }}"
                            width="400"
                            height="62"
                        />
                    </a>
                </td>
            </tr>
            <tr>
                <td><h2>Welcome {{ $user?->display_name }},</h2></td>
            </tr>
            <tr>
                <td>
                    We've heard you would like to try out our workshops and courses!
                </td>
            </tr>
            <tr>
                <td>
                    Before we can let you loose on our website, we need to make sure you are a real person and not a pesky robot or cat. Click this link <a href="https://www.stemmechanics.com.au/verify-email?code={{ $code }}">stemmechanics.com.au/verify-email</a> and if you are asked, use the confirm code:
                </td>
            </tr>
            <tr>
                <td
                    align="center"
                    style="
                        font-size: 200%;
                        text-align: center;
                        padding-top: 2rem;
                        padding-bottom: 2rem;
                        letter-spacing: 0.5rem;
                    "
                >
                    <strong>{{ $code }}</strong>
                </td>
            </tr>
            <tr>
                <td style="padding-bottom: 2rem">
                    But if you didn't ask to reset your password, you can delete
                    this email and your password will remain the same.
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
