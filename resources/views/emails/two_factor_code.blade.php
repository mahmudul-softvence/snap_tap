<!DOCTYPE html>
<html lang="bn">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="margin:0; padding:0; background-color:#f4f6f9; font-family: Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9; padding:20px;">
        <tr>
            <td align="center">

                <!-- Main Container -->
                <table width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:600px; background:#ffffff; border-radius:10px; overflow:hidden;">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="background:#2c3e50; padding:20px;">
                            <h2 style="color:#ffffff; margin:0; font-weight:500; font-size:20px;">
                                Security Verification
                            </h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:25px; color:#333333;">
                            <p style="font-size:16px; margin-top:0;">
                                Hello,
                            </p>

                            <p style="font-size:15px; line-height:1.6;">
                                Please use the verification code below to complete your login:
                            </p>

                            <!-- OTP -->
                            <div style="text-align:center; margin:25px 0;">
                                <span
                                    style="display:inline-block; font-size:26px; font-weight:bold; letter-spacing:6px; color:#2c3e50; background:#ecf0f1; padding:12px 25px; border-radius:8px;">
                                    {{ $code }}
                                </span>
                            </div>

                            <p style="font-size:14px; line-height:1.6;">
                                This code will expire in <strong>2 minutes</strong>.
                            </p>

                            <p style="font-size:14px; line-height:1.6;">
                                If you didn’t request this code, please ignore this email.
                            </p>

                            <p style="font-size:14px; margin-top:30px;">
                                Regards,<br>
                                <strong>{{ $platformName ?? 'SnapTap' }}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background:#f4f6f9; padding:15px; font-size:12px; color:#888;">
                            © {{ date('Y') }} {{ $platformName }}. All rights reserved.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
