<?php

namespace App\Http\Controllers;

use App\Domain\ShareToken;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\Draw;
use App\Models\ShareLink;
use App\Services\ShareLinkService;
use App\Services\ShareOgRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * F7-BE — SPEC-THE §5 + F7-CONTRACT §1/§2. Mỏng: validate ranh giới, đọc device,
 * delegate ShareLinkService (idempotency, hook, V5/V6/V7) + ShareOgRenderer (PNG).
 * API: POST /api/share-links, GET /api/share-links/{token}.
 * Web: GET /s/{token} (Blade fe-dev sở hữu view, BE sở hữu data), GET /s/{token}/cta,
 * GET /s/{token}/og.png. 404 nhẹ E3 — không lộ draw_id/device_id.
 */
class ShareLinkController extends Controller
{
    public function __construct(
        private readonly ShareLinkService $links,
        private readonly ShareOgRenderer $og,
    ) {
    }

    /** POST /api/share-links {draw_id} → 201 {token,url}; draw lạ của device → 404 NOT_FOUND. */
    public function store(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'draw_id' => ['required', 'integer', 'min:1'],
        ]);

        // draw không phải của device → KHÔNG phân biệt 403, trả 404 chung (E4 chống dò)
        $draw = Draw::query()
            ->where('id', $data['draw_id'])
            ->where('device_id', $device->device_id)
            ->first();
        if ($draw === null) {
            throw ApiException::notFound();
        }

        $link = $this->links->findOrCreate($device, $draw);

        return response()->json([
            'token' => $link->token,
            'url' => rtrim((string) config('app.url'), '/').'/s/'.$link->token,
        ], 201);
    }

    /** GET /api/share-links/{token} → payload công khai; token lạ → 404 nhẹ. */
    public function show(string $token): JsonResponse
    {
        $payload = $this->links->getPublic($token);
        if ($payload === null) {
            throw ApiException::notFound();
        }

        return response()->json($payload);
    }

    /**
     * GET /s/{token} — controller+data BE, view `share` Blade FE (F7-CONTRACT §1).
     * Đếm V5 có điều kiện 1/device/token/ngày. Token rác → share-404 nhẹ.
     */
    public function showPage(Request $request, string $token): Response
    {
        $payload = ShareToken::isValid($token) ? $this->links->getPublic($token) : null;
        if ($payload === null) {
            return response()->view('share-404', ['token' => null], 404);
        }

        /** @var Device $viewer */
        $viewer = $request->attributes->get('device');
        $this->links->recordView(
            ShareLink::query()->where('token', $token)->sole(),
            $viewer,
            (bool) $request->attributes->get('device_is_new', false),
            $request->headers->get('referer'),
        );

        // render lại payload SAU khi đếm (views +1 nếu lượt này được tính)
        $payload = $this->links->getPublic($token);

        return response()->view('share', [
            'token' => $token,
            'payload' => $payload,
            'ctaUrl' => '/s/'.$token.'/cta',
        ]);
    }

    /** GET /s/{token}/cta — V6 server-side, 302 → /app/draw + UTM khóa. */
    public function cta(Request $request, string $token): RedirectResponse|Response
    {
        $link = ShareToken::isValid($token)
            ? ShareLink::query()->where('token', $token)->first()
            : null;
        if ($link === null) {
            return response()->view('share-404', ['token' => null], 404);
        }

        /** @var Device $viewer */
        $viewer = $request->attributes->get('device');
        $this->links->recordCtaClick($link, $viewer);

        return redirect('/app/draw?utm_source=app_card&utm_medium=share&utm_campaign=share_card_v1', 302);
    }

    /** GET /s/{token}/og.png — 1200×630 GD, cache file 1 lần (ADR-002 §2). */
    public function ogPng(string $token): Response
    {
        $payload = ShareToken::isValid($token) ? $this->links->getPublic($token) : null;
        if ($payload === null) {
            // 404 nhẹ — KHÔNG ném ApiException: route này ngoài nhóm api/* nên
            // renderCallbacks §0.3 không bắt được → sẽ ra 500.
            return response()->view('share-404', ['token' => null], 404);
        }

        $png = $this->og->render($token, $payload);
        if ($png === null) {
            return response()->view('share-404', ['token' => null], 404);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
