<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:24px 0; background:#f3f4f6; color:#111827; font-family:Arial, Helvetica, sans-serif;">
    <table style="width:100%; border-collapse:collapse;" role="presentation">
        <tr>
            <td style="text-align:center;">
                <table style="width:570px; max-width:570px; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; border-collapse:collapse;" role="presentation">
                    <tr>
                        <td style="padding:28px 32px;">
                            <div style="text-align:center;">
                                <a href="{{ config('app.url') }}" target="_blank" rel="noopener">
                                    <img
                                        alt="STEMMechanics Logo"
                                        src="https://www.stemmechanics.com.au/logo.svg"
                                        width="200"
                                        height="31"
                                        style="border:0; display:inline-block; max-width:200px;"
                                    />
                                </a>
                            </div>

                            <p style="margin:24px 0 16px; font-size:14px; line-height:1.6;">Hi {{ $parentName }},</p>

                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6;">
                                {{ $childUsername }} has {{ $statusLabel }} a discussion {{ $activityLabel }}.
                            </p>

                            <p style="margin:0; font-size:14px; line-height:1.6;">
                                <strong>Category:</strong> {{ $categoryName }}<br />
                                <strong>Thread:</strong> {{ $topicTitle }}
                            </p>

                            @if($preview !== '')
                                <div style="margin:16px 0 0; padding:0 0 0 12px; border-left:4px solid #9ca3af; color:#374151; font-size:14px; line-height:1.7;">
                                    {!! nl2br(e($preview)) !!}
                                </div>
                            @endif

                            @if(!empty($approveUrl))
                                <table style="margin:24px auto 8px; border-collapse:collapse;" role="presentation">
                                    <tr>
                                        <td style="border-radius:4px; background-color:#48bb78; text-align:center;">
                                            <a href="{{ $approveUrl }}" target="_blank" rel="noopener" style="display:inline-block; padding:12px 22px; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none;">
                                                Approve {{ ucfirst($activityLabel) }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <p style="margin:24px 0 0; font-size:14px; line-height:1.6;">
                                Thanks,<br />
                                STEMMechanics
                            </p>
                        </td>
                    </tr>
                </table>

                <table style="width:570px; max-width:570px; border-collapse:collapse;" role="presentation">
                    <tr>
                        <td style="padding:16px 24px 0; color:#6b7280; font-size:12px; line-height:1.6; text-align:center;">
                            <a href="{{ route('index') }}" style="color:#6b7280; text-decoration:underline;">{{ config('app.name') }}</a>
                            | 63 Dalton Street | Westcourt, QLD 4870 Australia<br />
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
