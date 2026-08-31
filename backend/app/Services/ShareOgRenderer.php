<?php

namespace App\Services;

/**
 * F7-BE (ADR-002 §2) — OG image 1200×630 render server-side PHP GD, cache file
 * storage/app/share-og/{token}.png: render ĐÚNG 1 lần, token lạ → null (404).
 *Màu thẻ theo token 04-ui: nền paper #F7F2E7, chữ ink #1E1B18, symbol cinnabar
 * #B33A2B. Font: ưu tiên Noto (fc-list), thiếu → DejaVu system; glyph CJK ䷀
 * thiếu → vẽ bản chữ thay (tên quẻ), KHÔNG fail 500.
 */
class ShareOgRenderer
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const PAPER = [0xF7, 0xF2, 0xE7];

    private const INK = [0x1E, 0x1B, 0x18];

    private const CINNABAR = [0xB3, 0x3A, 0x2B];

    private const MUTED = [0x5C, 0x55, 0x4A];

    /**
     * Trả binary PNG (hoặc null = token không tồn tại). Cache hit → đọc file,
     * không re-render. Render lỗi bất kỳ (font/GD exception) → PNG nền trắng
     * tối giản, không 500 — OG chỉ là preview.
     */
    public function render(string $token, array $payload): ?string
    {
        $path = $this->cachePath($token);
        if (is_file($path)) {
            return (string) file_get_contents($path);
        }

        try {
            $png = $this->draw($payload);
        } catch (\Throwable $e) {
            report($e);
            $png = $this->blank();
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $png);

        return $png;
    }

    /** Xóa cache khi token bị xóa (E3 — ADR-002 consequences). */
    public function forget(string $token): void
    {
        $path = $this->cachePath($token);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function cachePath(string $token): string
    {
        // token đã isValid() ở controller; basename chống mọi dạng path traversal
        return storage_path('app/share-og/'.basename($token).'.png');
    }

    /** @param array{card:array<string,mixed>, sharer_label:string, views:int} $payload */
    private function draw(array $payload): string
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $paper = $this->color($im, self::PAPER);
        $ink = $this->color($im, self::INK);
        $cinnabar = $this->color($im, self::CINNABAR);
        $muted = $this->color($im, self::MUTED);

        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, $paper);
        // viền cinnabar mảnh 4px — nhận diện thương hiệu trên feed tối
        imagesetthickness($im, 4);
        imagerectangle($im, 2, 2, self::WIDTH - 3, self::HEIGHT - 3, $cinnabar);

        $card = $payload['card'];
        $titleFont = $this->fontPath(true);
        $bodyFont = $this->fontPath(false);
        $smallFont = $this->fontPath(false);

        // Symbol hoặc fallback chữ: glyph CJK không có trong font → chỉ tên quẻ.
        $symbol = (string) ($card['symbol'] ?? '');
        $hasSymbol = $symbol !== '' && $this->glyphAvailable($titleFont, $symbol);
        if ($hasSymbol) {
            $this->centerText($im, $titleFont, 180, $symbol, $cinnabar, 64);
        }
        $this->centerText($im, $titleFont, $hasSymbol ? 268 : 160,
            (string) ($card['ten'] ?? ''), $ink, 40);

        // ngày gieo + hook (clip ngắn GD — OG KHÔNG phải nơi clip80 chuẩn FE)
        $this->centerText($im, $smallFont, 320, 'Hôm nay: '.($card['drawn_date'] ?? ''), $muted, 22);
        $hook = trim((string) ($card['hook']['text'] ?? ''));
        if ($hook === '') {
            $hook = 'Gieo quẻ hôm nay — còn bạn?'; // E6 thẻ tối giản
        }
        $this->wrappedCenter($im, $bodyFont, $this->clipWords($hook, 90), $ink, 385, 40);

        // 4 keyword chips
        $y = 505;
        $keywords = array_slice((array) ($card['keywords'] ?? []), 0, 4);
        $totalW = 0;
        $widths = [];
        foreach ($keywords as $k => $word) {
            $bb = imagettfbbox(22, 0, $smallFont, $word);
            $widths[$k] = ($bb[2] - $bb[0]) + 36;
            $totalW += $widths[$k] + 16;
        }
        if ($totalW > 0) {
            $totalW -= 16;
            $x = intdiv(self::WIDTH - $totalW, 2);
            foreach ($keywords as $k => $word) {
                imagettftext($im, 22, 0, $x + 18, $y + 28, $muted, $smallFont, $word);
                $x += $widths[$k] + 16;
            }
        }

        // caption + footer
        $this->centerText($im, $smallFont, 575,
            'Quẻ của '.($payload['sharer_label'] ?? 'bạn').' hôm nay. Còn bạn?', $ink, 0);

        ob_start();
        imagepng($im, null, 6);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    private function blank(): string
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, $this->color($im, self::PAPER));
        ob_start();
        imagepng($im, null, 6);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    /** @param int[] $rgb */
    private function color(\GdImage $im, array $rgb): int
    {
        return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * Font hệ thống theo độ đậm: Noto (fc-list khai báo) trước, DejaVu fallback —
     * cả hai đều có trên máy chủ này. Trả đường dẫn .ttf tuyệt đối.
     */
    private function fontPath(bool $bold): string
    {
        $candidates = $bold
            ? ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
               '/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf']
            : ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
               '/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
               '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'];
        foreach ($candidates as $c) {
            if (is_file($c)) {
                return $c;
            }
        }

        throw new \RuntimeException('Không tìm thấy font system nào cho OG render');
    }

    /** imagettfbbox âm 1: ký tự có glyph thật (độ rộng > 0) hay ô tofu rỗng. */
    private function glyphAvailable(string $font, string $char): bool
    {
        $missing = imagettfbbox(10, 0, $font, '䷿');
        $probe = imagettfbbox(10, 0, $font, $char);
        // glyph thiếu trong font đơn byte (DejaVu/Liberation) cho CJK trả width
        // bằng đúng tofu — so với ký tự CJK chắc chắn thiếu khác chỉ là xấp xỉ;
        // cách chắc chắn: font không phải CJK-flag → coi như KHÔNG có glyph.
        $isCjkFamily = str_contains($font, 'NotoSerif') || str_contains($font, 'Cjk')
            || str_contains($font, 'UnifiedCJK');
        if (! $isCjkFamily) {
            return false;
        }

        return $probe !== false && $missing !== false && abs($probe[2] - $probe[0] - ($missing[2] - $missing[0])) > 1;
    }

    private function centerText(\GdImage $im, string $font, int $baseY, string $text, int $color, int $size): void
    {
        $pt = max(12, intdiv($size === 0 ? 24 : $size, 2));
        $bb = imagettfbbox($pt, 0, $font, $text);
        $w = $bb[2] - $bb[0];
        imagettftext($im, $pt, 0, intdiv(self::WIDTH - $w, 2), $baseY, $color, $font, $text);
    }

    /** Word-wrap 2 dòng tối đa, không cắt giữa từ (UTF-8 safe). */
    private function wrappedCenter(\GdImage $im, string $font, string $text, int $color, int $startY, int $lineH): void
    {
        $pt = 15;
        $maxW = self::WIDTH - 160;
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $try = $cur === '' ? $w : $cur.' '.$w;
            $bb = imagettfbbox($pt, 0, $font, $try);
            if ($bb[2] - $bb[0] > $maxW && $cur !== '') {
                $lines[] = $cur;
                $cur = $w;
            } else {
                $cur = $try;
            }
            if (count($lines) === 2) {
                break;
            }
        }
        if (count($lines) < 2 && $cur !== '') {
            $lines[] = $cur;
        }
        foreach ($lines as $i => $line) {
            $bb = imagettfbbox($pt, 0, $font, $line);
            imagettftext($im, $pt, 0, intdiv(self::WIDTH - ($bb[2] - $bb[0]), 2), $startY + $i * $lineH, $color, $font, $line);
        }
    }

    /** Clip theo ranhg giới TỪ (không bao giờ xén nửa word Việt/CJK). */
    private function clipWords(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $sp = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > 20) {
            $cut = mb_substr($cut, 0, $sp);
        }

        return $cut.'…';
    }
}
