<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Password Reset OTP</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
        <div style="background-color: #007bff; color: white; padding: 20px; text-align: center;">
            <h2>Password Reset Request</h2>
        </div>

        <div style="padding: 30px;">
            <p>Hi {{ $userName }},</p>

            <p>We received a request to reset your password. Use the OTP below to proceed:</p>

            <div
                style="background-color: #f8f9fa; border: 2px solid #007bff; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;">
                <p style="margin: 0; font-size: 12px; color: #666;">Your One-Time Password</p>
                <h1
                    style="margin: 10px 0; font-size: 36px; letter-spacing: 5px; color: #007bff; font-family: monospace;">
                    {{ $otp }}
                </h1>
                <p style="margin: 10px 0; font-size: 12px; color: #e74c3c;">
                    ⏱️ Expires in {{ $expiryMinutes }} minutes
                </p>
            </div>

            <p style="margin: 20px 0;">
                <strong>Important Security Notice:</strong>
            </p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Never share this OTP with anyone</li>
                <li>Our team will never ask for your OTP</li>
                <li>If you didn't request this, ignore this email</li>
            </ul>

            <p style="margin-top: 20px;">
                If you have trouble using this OTP, you can request a new one by trying to reset your password again.
            </p>
        </div>

        <div style="background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666;">
            <p style="margin: 0;">This is an automated email. Please do not reply to this message.</p>
        </div>
    </div>
</body>

</html>
