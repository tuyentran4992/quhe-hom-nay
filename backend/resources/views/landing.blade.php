{{-- [MKT-F2b] t_1bfee292 — Landing Blade server-render (06-mkt-tracking §1+§4).
     Token màu 100% theo 04-ui §1 / mockup duyệt s1-home — không tự chế. --}}
@php
    $ctaHref = '/app/' . (count($utm) ? '?' . http_build_query($utm) : '');
@endphp
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quẻ Hôm Nay — gieo quẻ Kinh Dịch miễn phí</title>
<meta name="description" content="Gieo quẻ Kinh Dịch miễn phí mỗi ngày, luận sâu từng hào động theo ngôn ngữ đời thường. Sản phẩm giải trí, tham khảo văn hoá.">
<meta name="robots" content="index, follow">
<meta property="og:title" content="Quẻ Hôm Nay — gieo quẻ Kinh Dịch miễn phí">
<meta property="og:description" content="Hôm nay bạn là quẻ gì? Gieo một quẻ, nhận luận sâu miễn phí.">
<meta property="og:type" content="website">
@if($ga4Id !== '')
<!-- GA4 (chỉ render khi env GA4_MEASUREMENT_ID khác rỗng — §1) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4Id }}"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}
gtag('js',new Date());gtag('config','{{ $ga4Id }}');
gtag('event','landing_visit',{{ \Illuminate\Support\Js::from($utm) }});
window.__qhnCta=function(){gtag('event','cta_gieo_que',{{ \Illuminate\Support\Js::from($utm) }})};
</script>
@else
<script>window.__qhnCta=function(){}</script>
@endif
<style>
:root{--ink:#1E1B18;--paper:#F7F2E7;--paper2:#EFE6D3;--cinnabar:#B33A2B;--gold:#A8802A;--bamboo:#3E5C48;--muted:#5C554A;--radius-card:14px;--gutter:20px;--shadow-card:0 1px 3px rgb(30 27 24 / .12);--shadow-warm:0 10px 26px rgb(30 27 24 / .13), 0 1px 3px rgb(30 27 24 / .10);--font-han:"Noto Serif TC",serif;--font-body:"Be Vietnam Pro",system-ui,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-body);font-size:16px;line-height:1.65;color:var(--ink);background:var(--paper);background-image:radial-gradient(120% 90% at 88% -6%, rgb(168 128 42 / .055), transparent 56%),radial-gradient(100% 70% at 4% 6%, rgb(179 58 43 / .045), transparent 52%),radial-gradient(110% 80% at 50% 118%, rgb(62 92 72 / .05), transparent 58%);min-height:100vh;display:flex;flex-direction:column;padding-bottom:58px}
.wrap{flex:1 0 auto;width:100%;max-width:640px;margin:0 auto;padding:48px var(--gutter) 24px;text-align:center}
.logo{font-family:var(--font-han);font-weight:700;font-size:22px;letter-spacing:.14em;color:var(--ink)}
.mark{width:96px;height:120px;margin:34px auto 22px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;background:linear-gradient(180deg,var(--paper),var(--paper2));border:1.5px solid rgb(168 128 42 / .65);border-radius:10px;box-shadow:inset 0 0 0 1px rgb(247 242 231 / .85),0 8px 18px rgb(30 27 24 / .12)}
.mark i{display:block;width:56px;height:6px;border-radius:2px;background:rgb(168 128 42 / .6)}
.mark i.yin{background:linear-gradient(90deg,rgb(168 128 42 / .6) 0 40%,transparent 40% 60%,rgb(168 128 42 / .6) 60% 100%)}
h1{font-size:26px;line-height:1.3;letter-spacing:.02em;font-weight:600}
.sub{color:var(--muted);margin:14px auto 0;max-width:46ch}
.sub b{color:var(--bamboo);font-weight:600}
.cta{display:inline-flex;align-items:center;gap:10px;margin-top:26px;font:600 16px/1 var(--font-body);letter-spacing:.02em;color:var(--paper);background:var(--cinnabar);border:1px solid rgb(168 128 42 / .55);border-radius:10px;padding:16px 26px;text-decoration:none;box-shadow:var(--shadow-warm),inset 0 0 0 1px rgb(247 242 231 / .18);transition:transform .25s ease,box-shadow .25s ease}
.cta:hover,.cta:focus-visible{transform:translateY(-2px);box-shadow:0 8px 20px rgb(179 58 43 / .32),inset 0 0 0 1px rgb(247 242 231 / .18);outline-offset:3px}
.oa{display:block;margin-top:18px;color:var(--bamboo);font-size:14.5px;font-weight:500;text-decoration:underline;text-underline-offset:3px}
.oa:hover{color:var(--ink)}
.card{margin-top:34px;background:var(--paper2);border:1px solid rgb(168 128 42 / .42);border-radius:var(--radius-card);box-shadow:var(--shadow-card);padding:22px var(--gutter);display:grid;gap:14px;text-align:left}
.card p{font-size:14.5px;color:var(--ink)}.card b{color:var(--cinnabar)}
footer{position:fixed;left:0;right:0;bottom:0;background:rgb(247 242 231 / .97);backdrop-filter:blur(6px);border-top:1px solid rgb(168 128 42 / .35);padding:10px var(--gutter);text-align:center;color:var(--muted);font-size:13.5px;line-height:1.5}
</style>
</head>
<body>
<div class="wrap">
  <header><span class="logo">Quẻ Hôm Nay</span></header>
  <div class="mark" aria-hidden="true"><i></i><i class="yin"></i><i></i><i></i><i class="yin"></i><i></i></div>
  <h1>Hôm nay bạn là quẻ gì?</h1>
  <p class="sub">Chạm một lần để gieo quẻ Kinh Dịch — nhận ngay đại ý, hào động và <b>luận sâu miễn phí</b> bằng tiếng Việt đời thường, mỗi ngày một quẻ.</p>
  <p><a class="cta" data-testid="landing-cta-draw" href="{{ $ctaHref }}" onclick="window.__qhnCta&&window.__qhnCta()">Gieo quẻ hôm nay →</a></p>
  <a class="oa" data-testid="landing-link-oa" href="{{ $oaUrl !== '' ? $oaUrl : '#' }}">Theo dõi quẻ mới trên Zalo OA →</a>
  <div class="card">
    <p><b>Miễn phí trọn phần luận.</b> Gieo quẻ, xem hào động, luận sâu — không thu phí, không phiên bản trả tiền.</p>
    <p><b>Nhẹ như một thú vui.</b> Gần 10 phút một ngày, đọc cho vui, biết cho rộng.</p>
  </div>
</div>
<footer>Sản phẩm giải trí, tham khảo văn hoá — không phải nghiên cứu hay tư vấn số mệnh.</footer>
<script>
(function(){var p=new URLSearchParams(location.search),u={};
["source","medium","campaign"].forEach(function(k){var v=p.get("utm_"+k);if(v)u[k]=v.slice(0,100)});
var b=JSON.stringify({name:'landing_visit',utm:u,props:{path:location.pathname,referrer:document.referrer||null}});
try{fetch("/api/track",{method:"POST",headers:{"Content-Type":"application/json"},body:b,credentials:"same-origin",keepalive:true}).catch(function(){})}catch(e){}})();
</script>
</body>
</html>
