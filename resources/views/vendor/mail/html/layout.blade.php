<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}
</style>
</head>
<body>
    <table class="wrapper" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <!-- Email Body -->
                <table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                    <!-- Body header -->
                    <tr>
                        <td class="header" align="center">
                            {{ $header ?? '' }}
                        </td>
                    </tr>

                    <!-- Body content -->
                    <tr>
                        <td class="content-cell">
                            {{ Illuminate\Mail\Markdown::parse($slot) }}
                        </td>
                    </tr>
                    @isset($subcopy)
                    <tr>
                        <td class="content-cell">
                            <hr />
                            {{ $subcopy ?? '' }}
                        </td>
                    </tr>
                    @endisset
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td class="content-cell" align="center">
                            {{ $footer ?? '' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
