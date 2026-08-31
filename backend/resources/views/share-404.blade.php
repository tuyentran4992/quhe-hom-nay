{{-- F7-FE (card/t_2e969791) — 404 nhẹ E3 (SPEC-THE §6): wording ĐÚNG nguyên văn
     "Thẻ này không còn, gieo thẻ mới nhé" + CTA GIỮ NGUYÊN bộ utm (dev-lead 18:15).
     KHÔNG lộ draw_id/device_id/nội khu (SharePageTest assert body sạch). --}}
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thẻ không còn — Quẻ Hôm Nay</title>
    <style>
        body { margin: 0; background: #F7F2E7; color: #1E1B18;
            font-family: 'Noto Serif TC', 'Times New Roman', serif;
            display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 24px; }
        main { max-width: 420px; text-align: center; }
        .symbol { font-size: 72px; color: #B08D3E; }
        p { font-size: 18px; line-height: 1.6; }
        a.cta { display: inline-block; margin-top: 18px; background: #B33A2B; color: #F7F2E7;
            text-decoration: none; font-weight: 600; padding: 14px 28px; border-radius: 12px; }
    </style>
</head>
<body>
    <main>
        <p class="symbol">䷺</p>
        <p>Thẻ này không còn, gieo thẻ mới nhé</p>
        {{-- CTA giữ nguyên bộ utm share_card_v1 (F7-CONTRACT §2) --}}
        <a class="cta" data-testid="share-page-cta"
           href="/app/draw?utm_source=app_card&amp;utm_medium=share&amp;utm_campaign=share_card_v1">Gieo quẻ hôm nay</a>
    </main>
</body>
</html>
