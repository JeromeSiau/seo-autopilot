<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Invitation</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f4f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="margin: 0 0 24px 0; font-size: 24px; font-weight: 600; color: #18181b; line-height: 1.3;">
                                You're invited to join {{ $teamName }}
                            </h1>

                            <p style="margin: 0 0 24px 0; font-size: 16px; color: #3f3f46; line-height: 1.6;">
                                You've been invited to join <strong>{{ $teamName }}</strong> as a <strong>{{ $role }}</strong>.
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 0 24px 0;">
                                <tr>
                                    <td style="border-radius: 6px; background-color: #16a34a;">
                                        <a href="{{ $acceptUrl }}" style="display: inline-block; padding: 14px 28px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none;">
                                            Accept Invitation
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 16px 0; font-size: 14px; color: #71717a; line-height: 1.5;">
                                This invitation expires on {{ $expiresAt }}.
                            </p>

                            <p style="margin: 0; font-size: 13px; color: #a1a1aa; line-height: 1.5;">
                                If you didn't expect this invitation, you can ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
