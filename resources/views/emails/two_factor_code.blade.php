<!DOCTYPE html>
<html lang="bn">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #333333;
        }

        p {
            font-size: 16px;
            color: #555555;
        }

        .code {
            display: inline-block;
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            background-color: #ecf0f1;
            padding: 10px 20px;
            margin: 20px 0;
            border-radius: 6px;
            letter-spacing: 3px;
        }

        .footer {
            font-size: 14px;
            color: #999999;
            margin-top: 30px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Your 2FA Code</h2>
        <p>Your two-factor authentication code is shown below:</p>
        <div class="code">{{ $code }}</div>
        <p>Please use this code within 2 minutes.</p>
        <div class="footer">
            If you did not request this code, no action is required.
        </div>
    </div>

</body>

</html>
