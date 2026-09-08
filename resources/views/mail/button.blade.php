{{-- 中央のボタンと、押せなかった場合の URL。$url と $label を渡すこと --}}
<p style="text-align: center; margin: 30px 0;">
    <a href="{{ $url }}"
       style="display: inline-block;
              background-color: #1976d2;
              color: #ffffff;
              padding: 12px 30px;
              text-decoration: none;
              border-radius: 2px;
              font-weight: bold;">
        {{ $label }}
    </a>
</p>

<p style="font-size: 0.9em; color: #666;">
    ボタンが機能しない場合は、以下のURLをブラウザにコピーしてください：<br>
    <a href="{{ $url }}" style="color: #1976d2;">{{ $url }}</a>
</p>
