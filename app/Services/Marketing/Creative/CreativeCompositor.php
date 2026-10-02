<?php

namespace App\Services\Marketing\Creative;

use App\Models\MarketingCreative;
use App\Services\Marketing\Ai\AiImageProviderManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders the final creative image with GD: base photo (portal asset or AI-
 * generated background) + brand overlays (scrim, logo, headline, price badge,
 * CTA pill, contact, disclaimer). Factual text comes ONLY from the creative's
 * resolved spec — never invented here. Falls back safely if GD/fonts/base image
 * are unavailable. Returns paths on the configured disk.
 */
class CreativeCompositor
{
    public function __construct(private AiImageProviderManager $imageProvider)
    {
    }

    /** @return array{render_path:string,thumb_path:string,width:int,height:int,warnings:array} */
    public function compose(MarketingCreative $creative): array
    {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('GD image extension is not available on the server.');
        }

        $dims = CreativeFormats::dimensions($creative->format ?: 'ig_1x1');
        $W = $dims['width'];
        $H = $dims['height'];
        $facts = (array) ($creative->spec['facts'] ?? []);
        $brand = (array) ($creative->spec['brand'] ?? []);
        $warnings = [];

        $canvas = imagecreatetruecolor($W, $H);

        // 1. Background
        [$base, $baseWarn] = $this->resolveBaseImage($creative, $facts);
        if ($baseWarn) {
            $warnings[] = $baseWarn;
        }
        if ($base) {
            $this->coverFit($canvas, $base, $W, $H);
            imagedestroy($base);
        } else {
            $this->gradientFill($canvas, $W, $H, $brand['secondary_color'] ?? '#0b1f3a', $brand['primary_color'] ?? '#2563eb');
        }

        // 2. Bottom scrim for legibility
        $this->bottomScrim($canvas, $W, $H);

        $fontBold = $this->font('bold');
        $fontReg = $this->font('regular');
        if (! $fontBold) {
            $warnings[] = 'No TrueType font available — text overlays were reduced. Set CREATIVE_FONT_BOLD.';
        }

        $textColor = $this->allocHex($canvas, $brand['text_color'] ?? '#ffffff');
        $accent = $brand['accent_color'] ?? '#f59e0b';
        $primary = $brand['primary_color'] ?? '#2563eb';

        $pad = (int) round($W * 0.06);
        $y = $H - $pad;

        // 3. Contact / disclaimer (bottom-most)
        if ($fontReg) {
            $contact = trim(implode('  ·  ', array_filter([
                $facts['whatsapp'] ?? $facts['phone'] ?? null,
                $facts['website'] ?? null,
            ])));
            if ($contact !== '') {
                $fs = max(14, (int) round($W * 0.022));
                imagettftext($canvas, $fs, 0, $pad, $y, $textColor, $fontReg, $contact);
                $y -= (int) round($fs * 1.8);
            }
            if (! empty($brand['default_disclaimer'])) {
                $fs = max(11, (int) round($W * 0.016));
                $muted = imagecolorallocatealpha($canvas, 255, 255, 255, 40);
                imagettftext($canvas, $fs, 0, $pad, $y, $muted, $fontReg, (string) $brand['default_disclaimer']);
                $y -= (int) round($fs * 1.8);
            }
        }

        // 4. CTA pill
        $cta = (string) ($creative->copy['cta'] ?? 'Book Now');
        if ($fontBold && $cta !== '') {
            $y -= (int) round($H * 0.02);
            $y = $this->ctaPill($canvas, $cta, $pad, $y, $W, $primary, $fontBold);
            $y -= (int) round($H * 0.03);
        }

        // 5. Headline (word-wrapped, above CTA)
        $headline = (string) ($creative->headline ?: ($facts['name'] ?? ''));
        if ($fontBold && $headline !== '') {
            $y = $this->headline($canvas, $headline, $pad, $y, $W, $textColor, $fontBold);
        }

        // 6. Price badge (top-right) — only if a real price exists
        if ($fontBold && ! empty($facts['price_formatted'])) {
            $this->priceBadge($canvas, $facts, $pad, $W, $accent, $fontBold);
        }

        // 7. Logo (top-left)
        $this->logo($canvas, $brand['logo_path'] ?? ($facts['logo_path'] ?? null), $pad, $W);

        // Save render (PNG) + thumbnail (JPEG)
        $disk = config('creative.disk', 'public');
        $folder = trim(config('creative.folder', 'marketing/creatives'), '/');
        $slug = Str::slug($creative->name ?: 'creative') ?: 'creative';
        $stub = $folder . '/' . $creative->id . '-' . $slug;

        $renderPath = $stub . '.png';
        ob_start();
        imagepng($canvas);
        Storage::disk($disk)->put($renderPath, ob_get_clean());

        // Thumbnail (max 480 wide)
        $thumbPath = $stub . '-thumb.jpg';
        $tw = 480;
        $th = (int) round($H * ($tw / $W));
        $thumb = imagecreatetruecolor($tw, $th);
        imagecopyresampled($thumb, $canvas, 0, 0, 0, 0, $tw, $th, $W, $H);
        ob_start();
        imagejpeg($thumb, null, (int) config('creative.quality', 90));
        Storage::disk($disk)->put($thumbPath, ob_get_clean());

        imagedestroy($thumb);
        imagedestroy($canvas);

        return [
            'render_path' => $renderPath,
            'thumb_path' => $thumbPath,
            'width' => $W,
            'height' => $H,
            'warnings' => $warnings,
        ];
    }

    // --- background --------------------------------------------------------

    /** @return array{0:\GdImage|null,1:?string} */
    private function resolveBaseImage(MarketingCreative $creative, array $facts): array
    {
        $source = $creative->spec['image_source'] ?? 'portal';

        // AI or combination: try AI first when configured.
        if (in_array($source, ['ai', 'combination'], true) && $this->imageProvider->isConfigured()) {
            try {
                $prompt = $this->imagePrompt($creative, $facts);
                $res = $this->imageProvider->generateImage($prompt, [
                    'size' => CreativeFormats::imageSize($creative->format ?: 'ig_1x1'),
                    'quality' => 'high',
                ]);
                $img = @imagecreatefromstring($res['bytes']);
                if ($img) {
                    // Persist the AI asset for the library (marked illustrative).
                    $this->storeAiAsset($creative, $res['bytes'], $prompt, $res);

                    return [$img, null];
                }
            } catch (\Throwable $e) {
                if ($source === 'ai') {
                    return [null, 'AI image generation failed (' . $e->getMessage() . '); used a branded background.'];
                }
                // combination → fall through to portal image
            }
        }

        // Portal image
        foreach (array_merge([$facts['cover_image'] ?? null], (array) ($facts['images'] ?? [])) as $path) {
            if (! $path) {
                continue;
            }
            $bytes = $this->readImageBytes($path);
            if ($bytes) {
                $img = @imagecreatefromstring($bytes);
                if ($img) {
                    return [$img, null];
                }
            }
        }

        if (($creative->spec['image_source'] ?? 'portal') === 'ai' && ! $this->imageProvider->isConfigured()) {
            return [null, 'No AI image provider is configured; used a branded background. Connect one in AI settings.'];
        }

        return [null, null];
    }

    private function imagePrompt(MarketingCreative $creative, array $facts): string
    {
        $style = $creative->style ?: 'premium';
        $dest = $facts['destination'] ?? $facts['name'] ?? 'a beautiful travel destination';
        $styleLabel = CreativeFormats::STYLES[$style] ?? 'premium';

        return "A {$styleLabel} travel advertising background photograph of {$dest}. "
            . 'High-end tourism aesthetic, natural lighting, no text, no watermark, no logos, '
            . 'leave the lower third relatively clear for overlay text. Photorealistic, vibrant but tasteful.';
    }

    private function readImageBytes(string $path): ?string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            try {
                $r = Http::timeout(30)->get($path);

                return $r->successful() ? $r->body() : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        // public disk storage path
        try {
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->get($path);
            }
        } catch (\Throwable $e) {
        }

        // bundled asset under public/ (e.g. images/...)
        $full = public_path(ltrim($path, '/'));
        if (is_file($full)) {
            return file_get_contents($full);
        }

        return null;
    }

    private function storeAiAsset(MarketingCreative $creative, string $bytes, string $prompt, array $res): void
    {
        try {
            $disk = config('creative.disk', 'public');
            $folder = trim(config('creative.folder', 'marketing/creatives'), '/') . '/ai';
            $path = $folder . '/' . $creative->id . '-' . Str::random(6) . '.png';
            Storage::disk($disk)->put($path, $bytes);

            $asset = \App\Models\MarketingAsset::create([
                'name' => 'AI · ' . ($creative->destination ?: $creative->name),
                'path' => $path,
                'mime_type' => 'image/png',
                'size' => strlen($bytes),
                'category' => 'ai_image',
                'source' => 'ai',
                'product_type' => $creative->product_type,
                'product_id' => $creative->product_id,
                'ai_generated' => true,
                'provider' => $res['provider'] ?? 'openai',
                'license_status' => 'illustrative',
                'status' => 'ready',
                'created_by' => $creative->created_by,
            ]);
            $creative->forceFill(['asset_id' => $asset->id])->saveQuietly();
        } catch (\Throwable $e) {
            // asset persistence is non-critical
        }
    }

    // --- GD drawing helpers ------------------------------------------------

    private function coverFit(\GdImage $canvas, \GdImage $src, int $W, int $H): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($W / $sw, $H / $sh);
        $nw = (int) ceil($sw * $scale);
        $nh = (int) ceil($sh * $scale);
        $dx = (int) round(($W - $nw) / 2);
        $dy = (int) round(($H - $nh) / 2);
        imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
    }

    private function gradientFill(\GdImage $canvas, int $W, int $H, string $c1, string $c2): void
    {
        [$r1, $g1, $b1] = $this->hex($c1);
        [$r2, $g2, $b2] = $this->hex($c2);
        for ($y = 0; $y < $H; $y++) {
            $t = $y / max(1, $H - 1);
            $col = imagecolorallocate($canvas,
                (int) round($r1 + ($r2 - $r1) * $t),
                (int) round($g1 + ($g2 - $g1) * $t),
                (int) round($b1 + ($b2 - $b1) * $t));
            imagefilledrectangle($canvas, 0, $y, $W, $y, $col);
        }
    }

    private function bottomScrim(\GdImage $canvas, int $W, int $H): void
    {
        $start = (int) round($H * 0.45);
        for ($y = $start; $y < $H; $y++) {
            $t = ($y - $start) / max(1, $H - $start);
            $alpha = (int) round(110 - $t * 110); // 110→0 (0=opaque)
            $col = imagecolorallocatealpha($canvas, 0, 0, 0, max(0, $alpha));
            imagefilledrectangle($canvas, 0, $y, $W, $y, $col);
        }
    }

    private function headline(\GdImage $canvas, string $text, int $pad, int $bottomY, int $W, int $color, string $font): int
    {
        $fs = max(28, (int) round($W * 0.058));
        $maxW = $W - $pad * 2;
        $lines = $this->wrap($text, $font, $fs, $maxW);
        $lineH = (int) round($fs * 1.25);
        $y = $bottomY;
        foreach (array_reverse($lines) as $line) {
            imagettftext($canvas, $fs, 0, $pad, $y, $color, $font, $line);
            $y -= $lineH;
        }

        return $y - (int) round($fs * 0.4);
    }

    private function ctaPill(\GdImage $canvas, string $text, int $pad, int $bottomY, int $W, string $color, string $font): int
    {
        $fs = max(16, (int) round($W * 0.026));
        $box = imagettfbbox($fs, 0, $font, $text);
        $tw = abs($box[2] - $box[0]);
        $th = abs($box[7] - $box[1]);
        $padX = (int) round($fs * 1.1);
        $padY = (int) round($fs * 0.7);
        $w = $tw + $padX * 2;
        $h = $th + $padY * 2;
        $x1 = $pad;
        $y1 = $bottomY - $h;
        [$r, $g, $b] = $this->hex($color);
        $fill = imagecolorallocate($canvas, $r, $g, $b);
        $this->roundedRect($canvas, $x1, $y1, $x1 + $w, $y1 + $h, (int) round($h / 2), $fill);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagettftext($canvas, $fs, 0, $x1 + $padX, $y1 + $padY + $th, $white, $font, $text);

        return $y1;
    }

    private function priceBadge(\GdImage $canvas, array $facts, int $pad, int $W, string $accent, string $font): void
    {
        $price = (string) $facts['price_formatted'];
        $label = ! empty($facts['discount']) ? ($facts['discount'] . '  ' . $price) : ('From ' . $price);
        $fs = max(18, (int) round($W * 0.03));
        $box = imagettfbbox($fs, 0, $font, $label);
        $tw = abs($box[2] - $box[0]);
        $th = abs($box[7] - $box[1]);
        $padX = (int) round($fs * 0.9);
        $padY = (int) round($fs * 0.6);
        $w = $tw + $padX * 2;
        $h = $th + $padY * 2;
        $x1 = $W - $pad - $w;
        $y1 = $pad;
        [$r, $g, $b] = $this->hex($accent);
        $fill = imagecolorallocate($canvas, $r, $g, $b);
        $this->roundedRect($canvas, $x1, $y1, $x1 + $w, $y1 + $h, (int) round($fs * 0.4), $fill);
        $dark = imagecolorallocate($canvas, 17, 24, 39);
        imagettftext($canvas, $fs, 0, $x1 + $padX, $y1 + $padY + $th, $dark, $font, $label);
    }

    private function logo(\GdImage $canvas, ?string $logoPath, int $pad, int $W): void
    {
        if (! $logoPath) {
            return;
        }
        $bytes = $this->readImageBytes($logoPath);
        if (! $bytes) {
            return;
        }
        $logo = @imagecreatefromstring($bytes);
        if (! $logo) {
            return;
        }
        $target = (int) round($W * 0.14);
        $lw = imagesx($logo);
        $lh = imagesy($logo);
        $scale = $target / max($lw, $lh);
        $nw = (int) round($lw * $scale);
        $nh = (int) round($lh * $scale);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $logo, $pad, $pad, 0, 0, $nw, $nh, $lw, $lh);
        imagedestroy($logo);
    }

    // --- primitives --------------------------------------------------------

    private function roundedRect(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledarc($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color, IMG_ARC_PIE);
    }

    private function wrap(string $text, string $font, int $fs, int $maxW): array
    {
        $words = preg_split('/\s+/', trim($text));
        $lines = [];
        $cur = '';
        foreach ($words as $word) {
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            $box = imagettfbbox($fs, 0, $font, $try);
            if (abs($box[2] - $box[0]) > $maxW && $cur !== '') {
                $lines[] = $cur;
                $cur = $word;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }

        return array_slice($lines, 0, 4);
    }

    private function font(string $which): ?string
    {
        $path = config("creative.fonts.{$which}");
        if ($path && is_file($path) && function_exists('imagettftext')) {
            return $path;
        }
        // Fallback to the other configured font.
        $other = config('creative.fonts.' . ($which === 'bold' ? 'regular' : 'bold'));

        return ($other && is_file($other)) ? $other : null;
    }

    private function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function allocHex(\GdImage $img, string $hex): int
    {
        [$r, $g, $b] = $this->hex($hex);

        return imagecolorallocate($img, $r, $g, $b);
    }
}
