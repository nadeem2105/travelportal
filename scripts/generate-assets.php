<?php

/**
 * Asset generator: creates the Kashmir-themed SVG artwork used by the
 * demo content. Run: php scripts/generate-assets.php
 * All images are deterministic (seeded) and stored in public/images.
 */

$dir = __DIR__ . '/../public/images';

@mkdir($dir . '/destinations', 0777, true);
@mkdir($dir . '/packages', 0777, true);
@mkdir($dir . '/guides', 0777, true);
@mkdir($dir . '/avatars', 0777, true);
@mkdir($dir . '/blogs', 0777, true);

function rng_make(int $seed): array
{
    return ['s' => $seed, 'i' => 0];
}

function rng_next(array &$r, int $min, int $max): int
{
    $r['s'] = ($r['s'] * 1103515245 + 12345) & 0x7fffffff;
    return $min + $r['s'] % ($max - $min + 1);
}

/** Layered mountain scene. $palettes: sky gradient, snow, peaks, tree, water */
function mountainScene(int $seed, int $w, int $h, array $palette, bool $lake = true, bool $boat = false, bool $gondola = false, bool $skier = false, float $treeLine = 0.62): string
{
    $r = rng_make($seed);
    [$skyA, $skyB, $peakFar, $peakNear, $snow, $tree, $waterA, $waterB] = $palette;

    $horizon = (int) ($h * 0.72);
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$w}' height='{$h}' viewBox='0 0 {$w} {$h}'>";

    // sky
    $svg .= "<defs>
        <linearGradient id='sky' x1='0' y1='0' x2='0' y2='1'>
            <stop offset='0' stop-color='{$skyA}'/><stop offset='1' stop-color='{$skyB}'/>
        </linearGradient>
        <linearGradient id='water' x1='0' y1='0' x2='0' y2='1'>
            <stop offset='0' stop-color='{$waterA}'/><stop offset='1' stop-color='{$waterB}'/>
        </linearGradient>
    </defs>";
    $svg .= "<rect width='{$w}' height='{$h}' fill='url(#sky)'/>";

    // sun glow
    $svg .= "<circle cx='" . ($w * 0.78) . "' cy='" . ($h * 0.18) . "' r='" . ($h * 0.11) . "' fill='#ffffff' opacity='0.35'/><circle cx='" . ($w * 0.78) . "' cy='" . ($h * 0.18) . "' r='" . ($h * 0.055) . "' fill='#fffdf5' opacity='0.8'/>";

    // far peaks
    $far = '';
    $x = -20;
    while ($x < $w + 40) {
        $pw = rng_next($r, (int) ($w * 0.12), (int) ($w * 0.22));
        $ph = rng_next($r, (int) ($h * 0.28), (int) ($h * 0.42));
        $far .= "L" . ($x + $pw / 2) . " " . ($horizon - $ph) . " L" . ($x + $pw) . " {$horizon} ";
        $x += $pw;
    }
    $svg .= "<path d='M-20 {$horizon} {$far} Z' fill='{$peakFar}' opacity='0.85'/>";

    // snowcaps on far peaks
    $x = -20;
    while ($x < $w + 40) {
        $pw = rng_next($r, (int) ($w * 0.12), (int) ($w * 0.22));
        $ph = rng_next($r, (int) ($h * 0.28), (int) ($h * 0.42));
        $tip = $horizon - $ph;
        $cap = min($h * 0.09, $ph * 0.28);
        $svg .= "<path d='M" . ($x + $pw / 2) . " {$tip} L" . ($x + $pw / 2 - $pw * 0.13) . " " . ($tip + $cap) . " L" . ($x + $pw / 2 - $pw * 0.04) . " " . ($tip + $cap * 0.62) . " L" . ($x + $pw / 2 + $pw * 0.05) . " " . ($tip + $cap) . " L" . ($x + $pw / 2 + $pw * 0.13) . " " . ($tip + $cap * 0.8) . " Z' fill='{$snow}' opacity='0.9'/>";
        $x += $pw;
    }

    // near ridge
    $near = '';
    $x = -30;
    while ($x < $w + 60) {
        $pw = rng_next($r, (int) ($w * 0.16), (int) ($w * 0.3));
        $ph = rng_next($r, (int) ($h * 0.16), (int) ($h * 0.3));
        $near .= "L" . ($x + $pw / 2) . " " . ($horizon - $ph) . " L" . ($x + $pw) . " {$horizon} ";
        $x += $pw;
    }
    $svg .= "<path d='M-30 {$horizon} {$near} Z' fill='{$peakNear}'/>";

    // gondola cable (Gulmarg)
    if ($gondola) {
        $cableY = (int) ($h * 0.3);
        $svg .= "<path d='M0 {$cableY} Q {$w}  " . ($cableY + $h * 0.04) . " {$w} {$cableY}' stroke='#5b6472' stroke-width='3' fill='none'/>";
        for ($i = 1; $i <= 3; $i++) {
            $gx = (int) ($w * $i / 4);
            $gy = $cableY + (int) sin($i / 4 * pi()) * $h * 0.03;
            $svg .= "<g><path d='M{$gx} " . ($gy - 18) . " l-16 26 h32 Z' fill='#e2e8f0'/><rect x='" . ($gx - 14) . "' y='" . ($gy + 6) . "' width='28' height='18' rx='6' fill='#c0392b'/><rect x='" . ($gx - 9) . "' y='" . ($gy + 10) . "' width='8' height='8' rx='2' fill='#f8fafc'/></g>";
        }
    }

    // trees
    $treeY = (int) ($h * $treeLine);
    $x = rng_next($r, 0, 40);
    while ($x < $w) {
        $th = rng_next($r, (int) ($h * 0.05), (int) ($h * 0.11));
        $tw = (int) ($th * 0.55);
        $ty = $treeY + rng_next($r, -8, 14);
        $shade = rng_next($r, 0, 1) ? $tree : '#2f5d3f';
        $svg .= "<path d='M{$x} {$ty} l{$tw} 0 l" . (-$tw / 2) . " -{$th} Z' fill='{$shade}'/><path d='M" . ($x + 2) . " " . ($ty - $th * 0.4) . " l" . ($tw - 4) . " 0 l" . (-($tw - 4) / 2) . " " . (-$th * 0.6) . " Z' fill='{$shade}' opacity='0.9'/>";
        $x += rng_next($r, 18, 55);
    }

    // meadow band
    $svg .= "<rect x='0' y='" . ($treeY + 6) . "' width='{$w}' height='" . ($horizon - $treeY) . "' fill='#57995f' opacity='0.55'/>";

    if ($skier) {
        $sx = (int) ($w * 0.7);
        $sy = (int) ($h * 0.62);
        $svg .= "<path d='M" . ($sx - 120) . " " . ($sy - 40) . " Q {$sx} {$sy} " . ($sx + 160) . " " . ($sy + 30) . "' stroke='#ffffff' stroke-width='10' fill='none' opacity='0.8'/>";
        $svg .= "<g><circle cx='{$sx}' cy='" . ($sy - 26) . "' r='9' fill='#fbbf24'/><path d='M" . ($sx - 10) . " " . ($sy - 12) . " l20 -4 l10 14 l-8 4 l-8 -8 l-12 8 Z' fill='#2563eb'/><path d='M" . ($sx - 22) . " " . ($sy + 2) . " l44 6' stroke='#0f172a' stroke-width='5' stroke-linecap='round'/><path d='M" . ($sx + 12) . " " . ($sy - 16) . " l14 -8' stroke='#0f172a' stroke-width='4' stroke-linecap='round'/></g>";
    }

    if ($lake) {
        // water
        $svg .= "<rect x='0' y='{$horizon}' width='{$w}' height='" . ($h - $horizon) . "' fill='url(#water)'/>";
        // ripples
        for ($i = 0; $i < 9; $i++) {
            $ry = $horizon + rng_next($r, 8, (int) (($h - $horizon) * 0.85));
            $rx = rng_next($r, 0, (int) ($w * 0.6));
            $rw = rng_next($r, (int) ($w * 0.08), (int) ($w * 0.3));
            $svg .= "<rect x='{$rx}' y='{$ry}' width='{$rw}' height='3' rx='1.5' fill='#ffffff' opacity='0.35'/>";
        }
        // peak reflection hint
        $svg .= "<path d='M" . ($w * 0.52) . " {$horizon} l" . ($w * 0.09) . " " . ($h * 0.2) . " l" . ($w * 0.09) . " -" . ($h * 0.2) . " Z' fill='{$snow}' opacity='0.12'/>";

        if ($boat) {
            // houseboat
            $bx = (int) ($w * 0.16);
            $by = (int) ($horizon + ($h - $horizon) * 0.42);
            $svg .= "<g>
                <rect x='{$bx}' y='" . ($by - 26) . "' width='130' height='16' rx='4' fill='#8a5a2b'/>
                <rect x='" . ($bx + 8) . "' y='" . ($by - 48) . "' width='114' height='24' rx='6' fill='#b98a55'/>
                <path d='M" . ($bx + 2) . " " . ($by - 48) . " q65 -26 130 0 Z' fill='#7c4a21'/>
                <rect x='" . ($bx + 20) . "' y='" . ($by - 42) . "' width='14' height='10' rx='2' fill='#fef3c7'/>
                <rect x='" . ($bx + 44) . "' y='" . ($by - 42) . "' width='14' height='10' rx='2' fill='#fef3c7'/>
                <rect x='" . ($bx + 68) . "' y='" . ($by - 42) . "' width='14' height='10' rx='2' fill='#fef3c7'/>
                <rect x='" . ($bx + 92) . "' y='" . ($by - 42) . "' width='14' height='10' rx='2' fill='#fef3c7'/>
                <rect x='" . ($bx - 6) . "' y='" . ($by - 10) . "' width='142' height='8' rx='3' fill='#5f3d1c'/>
            </g>";
            // shikara
            $sx2 = (int) ($w * 0.62);
            $sy2 = $by + 30;
            $svg .= "<g><path d='M{$sx2} {$sy2} q22 12 44 0 q-22 5 -44 0 Z' fill='#3b2a1a'/><path d='M" . ($sx2 + 21) . " " . ($sy2 - 2) . " l14 -18' stroke='#3b2a1a' stroke-width='3'/></g>";
        }
    }

    // birds
    for ($i = 0; $i < 3; $i++) {
        $bx = rng_next($r, (int) ($w * 0.3), (int) ($w * 0.7));
        $by = rng_next($r, (int) ($h * 0.12), (int) ($h * 0.3));
        $svg .= "<path d='M{$bx} {$by} q6 -6 12 0 q6 -6 12 0' stroke='#334155' stroke-width='2.5' fill='none' stroke-linecap='round'/>";
    }

    $svg .= '</svg>';

    return $svg;
}

$palettes = [
    'srinagar'   => ['#bfe3f7', '#e8f6ff', '#7f9fb8', '#4f7391', '#f2f8fc', '#33604a', '#7fb7d9', '#4d86ab', true, true],
    'gulmarg'    => ['#cfe0f2', '#eef6fc', '#93a7bd', '#5d7590', '#f5f9fd', '#3c6b50', '#a9c6dd', '#6f9cc0', true, false, true],
    'pahalgam'   => ['#c2e7db', '#eefaf3', '#89a79a', '#4f7464', '#f3faf6', '#2f5d3f', '#8fd0bd', '#5da693', false],
    'sonamarg'   => ['#d3e5f4', '#f0f7fd', '#a2b5c9', '#647e97', '#f7fafc', '#41684f', '#a9c9e0', '#77a2c4', true],
    'doodhpathri'=> ['#cdeee0', '#f0fbf5', '#95b3a4', '#54786a', '#f5fcf8', '#356347', '#a5d9c6', '#6fae9a', false],
    'yusmarg'    => ['#c5e9df', '#edfaf4', '#8aa898', '#4d7261', '#f4fbf7', '#31604a', '#93d2bd', '#5ba48f', false],
];

foreach ($palettes as $name => $p) {
    $seed = crc32($name);
    $opts = [$p[8], $p[9] ?? false, $p[10] ?? false];
    $svg = mountainScene($seed, 640, 400, [$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7]], ...$opts);
    file_put_contents("$dir/destinations/$name.svg", $svg);
}

// packages
$pkg = [
    'kashmir-delight' => ['srinagar', 640, 420],
    'honeymoon' => ['gulmarg', 640, 420],
    'family' => ['pahalgam', 640, 420],
    'gulmarg-winter' => ['gulmarg', 640, 420],
];
$winters = ['gulmarg-winter'];
foreach ($pkg as $slug => [$base, $w, $h]) {
    $palette = $palettes[$base];
    $isWinter = in_array($slug, $winters);
    if ($isWinter) {
        $palette = ['#dceaf8', '#f2f9ff', '#a9bcd0', '#718aa3', '#ffffff', '#5a7d92', '#b9d6ea', '#8fb4d1', true];
        $svg = mountainScene(crc32($slug), $w, $h, $palette, true, false, false, true, 0.7);
    } else {
        $svg = mountainScene(crc32($slug), $w, $h, [$palette[0], $palette[1], $palette[2], $palette[3], $palette[4], $palette[5], $palette[6], $palette[7]], true, $slug === 'kashmir-delight');
    }
    file_put_contents("$dir/packages/$slug.svg", $svg);
}

// hero: wide cinematic scene
$hero = mountainScene(20241225, 1920, 760, ['#a8d4f0', '#d9edfb', '#7e9db9', '#48688a', '#f4f9fd', '#2f5d40', '#6fb2dd', '#3f7dab'], true, true);
file_put_contents("$dir/hero.svg", $hero);

// generic scene reuse for guides/blogs
$guideTopics = ['best-places-kashmir', 'tulip-guide', 'winter-travel', 'houseboat-stay', 'trekking-kashmir', 'snowfall-guide', 'shopping-guide', 'food-guide'];
foreach ($guideTopics as $i => $topic) {
    $names = array_keys($palettes);
    $base = $palettes[$names[$i % count($names)]];
    file_put_contents("$dir/guides/$topic.svg", mountainScene(crc32($topic), 800, 480, [$base[0], $base[1], $base[2], $base[3], $base[4], $base[5], $base[6], $base[7]], $i % 2 === 0, $i % 3 === 0));
}
foreach (['valley-guide', 'shikara-ride', 'autumn-kashmir'] as $topic) {
    $names = array_keys($palettes);
    $base = $palettes[$names[crc32($topic) % count($names)]];
    file_put_contents("$dir/blogs/$topic.svg", mountainScene(crc32($topic), 800, 480, [$base[0], $base[1], $base[2], $base[3], $base[4], $base[5], $base[6], $base[7]], true, true));
}

// avatars
$avatarColors = [['#2563eb', '#93c5fd'], ['#0d9488', '#99f6e4'], ['#d97706', '#fde68a'], ['#db2777', '#fbcfe8'], ['#7c3aed', '#ddd6fe']];
foreach ($avatarColors as $i => [$c1, $c2]) {
    $initials = ['AS', 'RK', 'PM', 'NJ', 'ZM'][$i];
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='96' height='96' viewBox='0 0 96 96'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='{$c1}'/><stop offset='1' stop-color='{$c2}'/></linearGradient></defs><rect width='96' height='96' rx='48' fill='url(#g)'/><text x='48' y='58' font-family='Arial' font-size='30' font-weight='700' fill='#ffffff' text-anchor='middle'>{$initials}</text></svg>";
    file_put_contents("$dir/avatars/a" . ($i + 1) . ".svg", $svg);
}

// logo mark (mountain peak in rounded square)
$logo = "<svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' viewBox='0 0 64 64'>
  <defs><linearGradient id='lm' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='#3b82f6'/><stop offset='1' stop-color='#1d4ed8'/></linearGradient></defs>
  <rect x='2' y='2' width='60' height='60' rx='16' fill='url(#lm)'/>
  <path d='M12 46 L26 22 L34 34 L40 26 L52 46 Z' fill='#ffffff'/>
  <path d='M26 22 L31 30 L27 30 Z M40 26 L44 33 L38 33 Z' fill='#dbeafe'/>
</svg>";
file_put_contents("$dir/logo.svg", $logo);
@copy("$dir/logo.svg", "$dir/favicon.svg");

// QR placeholder (deterministic pattern)
$qrs = "<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160' viewBox='0 0 160 160'><rect width='160' height='160' fill='#ffffff'/>";
$qr = rng_make(777);
for ($y = 0; $y < 16; $y++) {
    for ($x = 0; $x < 16; $x++) {
        $inFinder = ($x < 5 && $y < 5) || ($x > 10 && $y < 5) || ($x < 5 && $y > 10);
        if (! $inFinder && rng_next($qr, 0, 1)) {
            $qrs .= "<rect x='" . ($x * 10) . "' y='" . ($y * 10) . "' width='10' height='10' fill='#0f172a'/>";
        }
    }
}
foreach ([[0, 0], [110, 0], [0, 110]] as [$fx, $fy]) {
    $qrs .= "<rect x='{$fx}' y='{$fy}' width='50' height='50' fill='#0f172a'/><rect x='" . ($fx + 10) . "' y='" . ($fy + 10) . "' width='30' height='30' fill='#ffffff'/><rect x='" . ($fx + 17) . "' y='" . ($fy + 17) . "' width='16' height='16' fill='#0f172a'/>";
}
$qrs .= '</svg>';
file_put_contents("$dir/qr.svg", $qrs);

echo "Assets generated in public/images\n";
