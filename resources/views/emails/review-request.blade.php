<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>

<body>
    <!-- Container Table-->
    <table bgcolor="#FFFFFF" width="100%">
        <tbody>
            <tr>
                <td style="font-family: 'Open sans', Arial, sans-serif;color: #000000;font-size: 14px;font-weight: 300;">
                    <!-- Inner Table-->
                    <table align="center" bgcolor="#ffffff" cellpadding="20" cellspacing="0" id="mainTable"
                        style="border:1px solid rgba(0,0,0,0.08);border-bottom:2px solid #E7E7E7;width:550px;margin:auto;min-height:450px;background-color:#fff;border-top-left-radius:6px;border-top-right-radius:6px;overflow:hidden"
                        width="550px">
                        <tbody>
                            <tr>
                                <td bgcolor="#ffffff" colspan="2"
                                    style="border-bottom: 0px solid #eaeaea;font-family: 'Open sans', Arial, sans-serif;color: #4b4b4b;font-size: 14px;font-weight: 300;">
                                    <table align="left" cellpadding="0" width="100%">
                                        <tbody>
                                            <tr>
                                                <td align="center"
                                                    style="margin: 0;font-family: 'Open sans', Arial, sans-serif;color: #4b4b4b;font-size: 14px;font-weight: 300;">
                                                    <img src="https://d19ayerf5ehaab.cloudfront.net/assets/upload-14652/fadd28d1d6efd6ea4d59f593e79e702f1620913807.png"
                                                        width="240">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td align="center"
                                    style="padding: 0;font-family: 'Open sans', Arial, sans-serif;font-size: 14px;font-weight: 300;">
                                    <a href="#" title="Read Reviews"><img
                                            src="https://d19ayerf5ehaab.cloudfront.net/assets/upload-14652/8cb494372e17193c7cea4599bc270bdc1620914646.png"
                                            width="100%"></a>
                                </td>
                            </tr>
                            <tr>
                                <td align="left" colspan="2" width="100%"
                                    style="font-family: 'Open sans', Arial, sans-serif;color: #000000;font-size: 14px;font-weight: 300;">
                                    <p
                                        style="color:#000000;font-family:'Open Sans', sans-serif;font-weight: 400; font-size: 16px; text-align: left">
                                        Hi {{ $review->name }},</p>
                                    <p
                                        style="color:#000000;font-family:'Open Sans', sans-serif;font-weight: 400; font-size: 16px; text-align: left">
                                        {!! nl2br(e($review->message)) !!}</p>
                                </td>
                            </tr>
                            {{-- <tr>
                                <td colspan="2"
                                    style="background: #ffffff;height: 100px;font-family: 'Open sans', Arial, sans-serif;color: #4b4b4b;font-size: 14px;font-weight: 300;">
                                    <center>
                                        <table align="center" bgcolor="#FFFFFF" cellpadding="0"
                                            style="text-decoration: none;font-size: 12px;background-color: #0000FF;border:1px solid #407ec5; border-radius: 7px; padding-top:11px; padding-bottom:11px;"
                                            width="35%">
                                            <tbody>
                                                <tr>
                                                    <td align="center" valign="middle"
                                                        style="font-family: 'Open sans', Arial, sans-serif;color: #4b4b4b;font-size: 14px;font-weight: 600;">
                                                        <a href="[link]"
                                                            style="font-family:'Open Sans', sans-serif;font-weight: 600;white-space: nowrap; text-decoration: none; color: #FFFFFF; font-size: 17px;  border-radius: 7px !important;">Write
                                                            Review</a>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </center>
                                </td>
                            </tr> --}}
                            <tr>
                                <td align="center" colspan="2" width="100%"
                                    style="padding-top: 0;font-family: 'Open sans', Arial, sans-serif;color: #4b4b4b;font-size: 14px;font-weight: 300;">
                                    <a style="color:#0000FF;font-family:'Open Sans', sans-serif;font-weight: 800; font-size: 14px; text-align: center; line-height: 24px;"
                                        href="{{ $reviewLink }}">I already reviewed this</a>
                                </td>
                            </tr>

                            <tr bgcolor="0000FF">
                                <td colspan="2"
                                    style="font-family: 'Open sans', Arial, sans-serif;color: #4b4b4b;font-size: 14px;font-weight: 300;">
                                    <center>
                                        <p
                                            style="text-align: center;font-size: 15px;font-weight: bold;color: #FFFFFF;margin: 0px !important;padding: 0px !important;line-height: inherit !important;border-collapse: collapse;mso-line-height-rule: exactly;font-family: 'Open sans', Arial, sans-serif;">
                                            The Woof<br>The woof woof of the woof world</p>
                                    </center>
                                </td>
                            </tr>
                            <tr bgcolor="444444">
                                <td colspan="2"
                                    style="font-family: 'Open sans', Arial, sans-serif;color: #4b4b4b;font-size: 14px;font-weight: 300;">
                                    <center>
                                        {{-- <p
                                            style="text-align: center;font-size: 10px;color: #DDDDDD;margin: 0px !important;padding: 0px !important;line-height: inherit !important;border-collapse: collapse;mso-line-height-rule: exactly;font-family: 'Open sans', Arial, sans-serif;font-weight: 300;">
                                            Review collection powered by <a href="https://www.reviews.io"
                                                style="color:#FFFFFF;text-decoration:none;font-weight:bold;">Reviews.io</a>
                                        </p> --}}
                                    </center>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    &nbsp;<br>
                    <!-- Inner Table-->
                </td>
            </tr>
        </tbody>
    </table>
    <!-- Container Table-->

</body>

</html>
