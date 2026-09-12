{{-- ChreeID から送るメールの外枠。DokuFarm / WikiChree のメールと体裁を揃える。
     メールクライアントは <style> や外部 CSS を落とすので、すべてインラインで書くこと。 --}}
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>
<body style="font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333;">
<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #1976d2; border-bottom: 2px solid #1976d2; padding-bottom: 10px;">ChreeID</h1>

    @yield('body')

    <p style="color: #999; font-size: 0.85em; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px;">
        @yield('note', __('mail.common.default_note'))<br>
        --<br>
        ChreeID by Team WikiChree.COM
    </p>
</div>
</body>
</html>
