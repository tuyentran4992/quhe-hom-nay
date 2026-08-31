{{-- F7-BE placeholder — VIEW thật là của fe-dev (card/t_2e969791, lane F7-CONTRACT §1).
     BE chỉ đảm bảo: HTTP 200 + data công khai trong $payload, OG meta trỏ
     /s/{token}/og.png, CTA trỏ $ctaUrl. KHÔNG nhúng nội dung suy diễn/luận
     giải — chỉ đúng các trường có trong payload. --}}
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $payload['card']['symbol'] }} · {{ $payload['card']['ten'] }} — Quê Hom Nay</title>
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $payload['card']['symbol'] }} {{ $payload['card']['ten'] }}: {{ $payload['card']['hook']['text'] }}">
    <meta property="og:description" content="{{ $payload['card']['disclaimer'] }}">
    <meta property="og:image" content="{{ url('/s/' . $token . '/og.png') }}">
    <meta property="og:url" content="{{ url('/s/' . $token) }}">
</head>
<body>
    <main id="share-card" data-token="{{ $token }}">
        <h1>{{ $payload['card']['symbol'] }} {{ $payload['card']['ten'] }}</h1>
        <p>{{ $payload['card']['hook']['text'] }}</p>
        <a href="{{ $ctaUrl }}">Xem quẻ của bạn</a>
    </main>
</body>
</html>
