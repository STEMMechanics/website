<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:24px 0; background:#f8fafc; color:#0f172a; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:1028px; margin:0 auto;">
        <tr>
            <td>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 auto 20px auto;">
                    <tr>
                        <td style="background:#0f172a; border-radius:12px; padding:24px 28px;">
                            <a href="{{ config('app.url') }}" target="_blank" rel="noopener" style="display:inline-block; text-decoration:none;">
                                <img
                                    alt="STEMMechanics Logo"
                                    src="{{ asset('/logo-dark.png') }}"
                                    width="200"
                                    height="36"
                                    style="border:0; display:block; width:200px; height:36px;"
                                />
                            </a>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="padding:32px 34px; font-size:14px; line-height:1.7; color:#334155;">
                            <p style="margin:0 0 16px;">Hi {{ $parentName }},</p>

                            <p style="margin:0 0 16px;">
                                {{ $childUsername }} has {{ $statusLabel }} a discussion {{ $activityLabel }}.
                            </p>

                            <p style="margin:0 0 16px;">
                                <strong>Category:</strong> {{ $categoryName }}<br />
                                <strong>Thread:</strong> {{ $topicTitle }}
                            </p>

                            @if($preview !== '')
                                <div style="margin:18px 0 0; padding:0 0 0 12px; border-left:4px solid #cbd5e1; color:#475569;">
                                    {!! nl2br(e($preview)) !!}
                                </div>
                            @endif

                            @if(!empty($approveUrl))
                                <p style="margin:24px 0 0;">
                                    <a href="{{ $approveUrl }}" target="_blank" rel="noopener" class="button button-success" style="display:inline-block; text-decoration:none; background:#16a34a; color:#ffffff; font-size:14px; font-weight:800; padding:12px 18px; border-radius:12px;">Approve {{ ucfirst($activityLabel) }}</a>
                                </p>
                            @endif

                            <p style="margin:24px 0 0;">
                                Thanks,<br />
                                STEMMechanics
                            </p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 auto; background:#f3f4f6; border-radius:0 0 12px 12px;">
                    <tr>
                        <td align="center" style="padding:22px 28px 26px 28px;">
                            <div style="font-size:12px; line-height:1.7; color:#6b7280; text-align:center;">
                                <a href="{{ route('index') }}" style="color:#0284c7; text-decoration:underline;">{{ config('app.name') }}</a>
                                | 63 Dalton Street | Westcourt, QLD 4870 Australia<br />
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
