<!DOCTYPE html>
<html lang="lt" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>{{ $mailBrandName }}</title>
<style type="text/css">
.transactional-md p { margin:0 0 16px; line-height:1.5; color:#718096; font-size:16px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; }
.transactional-md p:last-child { margin-bottom:0; }
.transactional-md a { color:#1946a0 !important; font-weight:600; text-decoration:none !important; }
.transactional-md strong { color:#3d4852; font-weight:700; }
.transactional-md em { font-style:italic; }
.transactional-md h1, .transactional-md h2, .transactional-md h3 { color:#3d4852; margin:0 0 12px; font-size:18px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; }
.transactional-md ul, .transactional-md ol { margin:0 0 16px; padding-left:24px; color:#718096; font-size:16px; }
.transactional-md li { margin-bottom:6px; }
.transactional-md blockquote { border-left:4px solid #1946a0; margin:16px 0; padding-left:16px; color:#718096; }
.transactional-md code { font-size:14px; background:#edf2f7; padding:2px 6px; border-radius:3px; }
</style>
</head>
<body style="margin:0;padding:0;background-color:#edf2f7;-webkit-text-size-adjust:none;">
<table role="presentation" class="wrapper" width="100%" cellpadding="0" cellspacing="0" style="background-color:#edf2f7;margin:0;padding:0;width:100%;">
<tr>
<td align="center" style="padding:24px 12px;">
<table role="presentation" class="inner-body" width="570" cellpadding="0" cellspacing="0" style="max-width:570px;width:100%;background-color:#ffffff;border-color:#e8e5ef;border-radius:2px;border-style:solid;border-width:1px;box-shadow:0 2px 0 rgba(0,0,150,0.025),2px 4px 0 rgba(0,0,150,0.015);margin:0 auto;">
<tr>
<td class="header" style="padding:25px 0;text-align:center;">
<a href="{{ config('app.url') }}" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:19px;font-weight:bold;color:#1946a0;text-decoration:none;">{{ $mailBrandName }}</a>
</td>
</tr>
<tr>
<td class="content-cell" style="padding:32px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.5;color:#718096;">
<div class="transactional-md" style="word-break:break-word;">{!! $contentHtml !!}</div>
@if (!empty($actionUrl))
<table role="presentation" class="action" align="center" width="100%" cellpadding="0" cellspacing="0" style="margin:30px auto 0;padding:0;text-align:center;width:100%;">
<tr>
<td align="center">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="border-radius:4px;background-color:#1946a0;">
<a href="{{ $actionUrl }}" target="_blank" rel="noopener" style="display:inline-block;padding:10px 24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;font-weight:600;color:#ffffff;text-decoration:none;">{{ $actionLabel }}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
<p style="margin:24px 0 0;font-size:14px;line-height:1.45;color:#718096;">Jei mygtukas neveikia, spauskite <a href="{{ $actionUrl }}" target="_blank" rel="noopener" style="color:#1946a0;font-weight:700;text-decoration:none;">{{ $mailBrandName }}</a>.</p>
@endif
</td>
</tr>
<tr>
<td style="padding:20px 32px 28px;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#b0adc5;">
© {{ date('Y') }} {{ $mailBrandName }}. Visos teisės saugomos.
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
