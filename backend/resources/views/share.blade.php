{{-- F7-FE (card/t_2e969791) — trang public /s/{token}. BE sở hữu controller + data
     ($token, $payload{card,sharer_label,views}, $ctaUrl — F7-CONTRACT §1); VIEW là fe-dev.
     Luật hiển thị: CHỈ các trường có trong payload — KHÔNG free_content/han/quoc_am/luận sâu
     (SPEC-THE §2 chống lộ). CTA data-testid="share-page-cta" (testid §5) href $ctaUrl
     (BE 302 + V6 server-side — không JS). Auto-redirect CẤM (bất biến SPEC §4).
     Og:description dùng dai_ci NGUYÊN VĂN là việc controller/og renderer BE; blade chỉ
     OG meta theo field payload có sẵn. --}}
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $card = $payload['card'];
        $ogUrl = url('/s/' . $token);
    @endphp
    <title>{{ $card['symbol'] }} {{ $card['ten'] }} — Quẻ Hôm Nay</title>
    <meta name="description" content="{{ $card['hook']['text'] }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Quẻ Hôm Nay">
    <meta property="og:title" content="{{ $card['symbol'] }} {{ $card['ten'] }}">
    <meta property="og:description" content="{{ $card['hook']['text'] }}">
    <meta property="og:image" content="{{ url($card['qr_text'] . '/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta name="twitter:card" content="summary_large_image">
    <style>
        /* Token 04-ui §1 (inline — trang public 1 file, không build pipeline). */
        :root {
            --paper: #F7F2E7; --paper2: #EFE7D7; --ink: #1E1B18;
            --muted: #5C554A; --cinnabar: #B33A2B; --bamboo: #3E5C48; --gold: #B08D3E;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--paper); color: var(--ink);
            font-family: 'Noto Serif TC', 'Times New Roman', serif;
            display: flex; min-height: 100vh; align-items: center; justify-content: center;
            padding: 24px;
        }
        main { max-width: 480px; width: 100%; text-align: center; }
        .symbol { font-size: 96px; line-height: 1; color: var(--cinnabar); }
        .ten { font-size: 26px; font-weight: 600; margin-top: 8px; }
        .date { color: var(--muted); font-size: 14px; margin-top: 4px; }
        .hook { font-size: 19px; line-height: 1.55; margin-top: 20px; }
        .chips { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 18px; }
        .chip {
            border: 1px solid var(--bamboo); color: var(--bamboo); background: var(--paper2);
            border-radius: 999px; padding: 4px 14px; font-size: 13px;
        }
        .from { margin-top: 26px; font-size: 15px; color: var(--ink); }
        .cta {
            display: inline-block; margin-top: 16px; background: var(--cinnabar); color: var(--paper);
            text-decoration: none; font-weight: 600; font-size: 17px; padding: 14px 28px; border-radius: 12px;
        }
        .views { margin-top: 12px; color: var(--muted); font-size: 12px; }
        .disclaimer { margin-top: 22px; color: var(--muted); font-size: 11px; }
    </style>
</head>
<body>
    <main id="share-card" data-token="{{ $token }}">
        <p class="symbol">{{ $card['symbol'] }}</p>
        <h1 class="ten">{{ $card['ten'] }}</h1>
        <p class="date">Hôm nay {{ $card['drawn_date'] }}</p>
        @if ($card['hook']['text'] !== '')
            <p class="hook">“{{ $card['hook']['text'] }}”</p>
        @endif
        @if (count($card['keywords']))
            <div class="chips">
                @foreach (array_slice($card['keywords'], 0, 4) as $k)
                    <span class="chip">{{ $k }}</span>
                @endforeach
            </div>
        @endif
        {{-- CAP-THE §4: dòng gọi mời + label người chia sẻ (payload.sharer_label) --}}
        <p class="from">Quẻ của {{ $payload['sharer_label'] }} hôm nay. Còn bạn?</p>
        <a class="cta" data-testid="share-page-cta" href="{{ $ctaUrl }}">Gieo quẻ của bạn</a>
        @if ($payload['views'] > 0)
            <p class="views">{{ number_format((int) $payload['views'], 0, ',', '.') }} lượt xem thẻ này</p>
        @endif
        <p class="disclaimer">{{ $card['disclaimer'] }}</p>
    </main>
</body>
</html>
