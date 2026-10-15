<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Usage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VideoComposerAgent
{
    public function render(array &$snapshot, array $artifacts, int $contentId, array $locks = []): array
    {
        // Protect the VPS even if an extra worker is accidentally started.
        return Cache::lock('affiliate-agent:ffmpeg', 900)->block(10, function () use (&$snapshot, $artifacts, $contentId, $locks) {
            $start = microtime(true);
            $tmp = storage_path('app/agent-tmp/'.Str::uuid());
            mkdir($tmp, 0755, true);
            try {
                $duration = array_sum(array_column($snapshot['scenes'], 'duration'));
                $voice = app(AssetStore::class)->absolute($artifacts['voice']);
                $probe = new Process([config('affiliate_agent.ffprobe'), '-v', 'error', '-show_entries', 'format=duration', '-of', 'json', $voice]);
                $probe->setTimeout(30)->mustRun();
                $voiceDuration = (float) (json_decode($probe->getOutput(), true)['format']['duration'] ?? 0);
                if ($voiceDuration <= 0) throw new \RuntimeException('Could not determine the narration duration.');
                if ($voiceDuration > 45) throw new \RuntimeException('Narration exceeds maximum 45-second video duration. Please shorten spoken script.');
                $timingLocked = in_array('scenes', $locks, true) || collect($locks)->contains(fn ($lock) => str_starts_with($lock, 'scenes.'));
                if ($voiceDuration > $duration) {
                    if ($timingLocked) throw new \RuntimeException('Narration needs longer scenes, but scene timing is locked.');
                    $target = min(45, max($duration, $voiceDuration + 0.15));
                    $count = count($snapshot['scenes']);
                    $minimum = max(1, min(2.5, $target / $count * 0.65));
                    $weights = array_map(fn ($scene) => max(1, count(preg_split('/\s+/u', trim($scene['text'])))), $snapshot['scenes']);
                    $weightTotal = array_sum($weights);
                    $elapsed = 0;
                    foreach ($snapshot['scenes'] as $i => &$scene) {
                        $scene['duration'] = $i === $count - 1 ? round($target - $elapsed, 3) : round($minimum + ($target - $minimum * $count) * $weights[$i] / $weightTotal, 3);
                        $elapsed += $scene['duration'];
                    }
                    unset($scene);
                    $duration = $target;
                }
                $command = [config('affiliate_agent.ffmpeg'), '-y'];
                $filters = [];
                $darkInk = [];
                foreach ($snapshot['scenes'] as $index => $scene) {
                    $asset = $artifacts['scenes'][$scene['id']] ?? null;
                    $solid = in_array($scene['background_type'] ?? '', ['solid_light', 'solid_dark'], true);
                    if ($asset && !$solid && ($scene['background_type'] ?? 'media') !== 'animated_abstract') {
                        $command = [...$command, ...($asset['mime_type'] === 'video/mp4' ? ['-stream_loop', '-1'] : ['-loop', '1']), '-i', app(AssetStore::class)->absolute($asset)];
                    } elseif ($solid) {
                        $color = $scene['background_type'] === 'solid_light' ? '0xf4f3ec' : '0x111924';
                        array_push($command, '-f', 'lavfi', '-i', "color=c=$color:s=1080x1920:r=30");
                    } else {
                        $background = $tmp.'/background-'.$index.'.ppm';
                        $this->gradient($background, $scene['visual_style'] ?? 'abstract', $index);
                        array_push($command, '-loop', '1', '-i', $background);
                    }
                    $videoMedia = $asset && $asset['mime_type'] === 'video/mp4' && !$solid && ($scene['background_type'] ?? '') === 'media';
                    $motion = $this->backgroundMotion($scene, $solid, $videoMedia);
                    $demo = ($scene['purpose'] ?? '') === 'demo';
                    $brightness = $asset && !$solid ? $this->brightness(app(AssetStore::class)->absolute($asset)) : null;
                    $automatic = $brightness === null ? ($asset ? .38 : .18) : ($brightness > 160 ? .54 : ($brightness < 75 ? .18 : .34));
                    $opacity = match ($scene['overlay_mode'] ?? 'auto') {
                        'light' => .22, 'medium' => .38, 'strong' => .58, 'none' => 0.0, default => $automatic,
                    };
                    $darkInk[$scene['id']] = ($scene['background_type'] ?? '') === 'solid_light' || ($asset && !$solid && $brightness !== null && $brightness * (1 - $opacity) > 145);
                    // A single full-frame tint preserves the selected media across the entire scene.
                    $shade = $solid || $opacity === 0.0 ? '' : ',drawbox=x=0:y=0:w=iw:h=ih:color=black@'.($demo ? min(.66, $opacity + .08) : $opacity).':t=fill';
                    $filters[] = '['.$index.':v]'.$motion.',setsar=1,fps=30,trim=duration='.$scene['duration'].',setpts=PTS-STARTPTS'.$shade.'[v'.$index.']';
                }
                $n = count($snapshot['scenes']);
                array_push($command, '-i', $voice);
                $captionsPath = $tmp.'/captions.ass';
                file_put_contents(
                    $captionsPath,
                    $this->subtitles($snapshot, !empty($artifacts['voice']['mock']), $darkInk)
                );
                $inputs = implode('', array_map(fn ($i) => "[v$i]", range(0, $n - 1)));
                $captionFilterPath = $this->ffmpegFilterPath(basename($captionsPath));
                $filters[] = "$inputs concat=n=$n:v=1:a=0,subtitles=filename='$captionFilterPath'[out]";
                $filters[] = "[$n:a]apad,atrim=duration={$duration}[aout]";
                array_push($command, '-filter_complex_threads', '1', '-filter_complex', implode(';', $filters), '-map', '[out]', '-map', '[aout]', '-t', (string) $duration, '-c:v', 'libx264', '-threads', '2', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', $tmp.'/master.mp4');
                $p = new Process($command, $tmp);
                $p->setTimeout(config('affiliate_agent.render_timeout'))->mustRun();
                $asset = app(AssetStore::class)->put(file_get_contents($tmp.'/master.mp4'), 'mp4', 'video/mp4', !empty($artifacts['voice']['mock']));
                Usage::create(['content_id' => $contentId, 'provider' => 'ffmpeg', 'operation' => 'render', 'duration_seconds' => microtime(true) - $start, 'mock' => $asset['mock']]);
                return $asset;
            } finally {
                foreach (glob($tmp.'/*') ?: [] as $f) unlink($f);
                if (is_dir($tmp)) rmdir($tmp);
            }
        });
    }

    private function ffmpegFilterPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (preg_match('/^[A-Za-z]:\//', $path)) {
            $path = substr($path, 0, 1).'\\\\:'.substr($path, 2);
        }

        return str_replace("'", "\\'", $path);
    }

    private function backgroundMotion(array $scene, bool $solid, bool $videoMedia): string
    {
        if ($solid) return 'scale=1080:1920';
        $focus = $scene['media_focus'] ?? 'center';
        $x = match ($focus) { 'left' => '0', 'right' => 'in_w-out_w', default => '(in_w-out_w)/2' };
        $y = match ($focus) { 'top' => '0', 'bottom' => 'in_h-out_h', default => '(in_h-out_h)/2' };
        if (($scene['media_fit'] ?? 'cover') === 'contain') {
            $px = match ($focus) { 'left' => '0', 'right' => 'ow-iw', default => '(ow-iw)/2' };
            $py = match ($focus) { 'top' => '0', 'bottom' => 'oh-ih', default => '(oh-ih)/2' };
            return "scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:x=$px:y=$py:color=0x111924";
        }
        if ($videoMedia) return "scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920:x='$x':y='$y'";
        if (in_array($focus, ['center', 'top', 'bottom'], true)) $x .= '+24*sin(n/60)';
        if (in_array($focus, ['center', 'left', 'right'], true)) $y .= '+20*cos(n/75)';
        return "scale=1200:2134:force_original_aspect_ratio=increase,crop=1080:1920:x='$x':y='$y'";
    }

    private function brightness(string $path): ?float
    {
        try {
            $probe = new Process([config('affiliate_agent.ffmpeg'), '-v', 'error', '-i', $path, '-vf', 'scale=1:1,format=rgb24', '-frames:v', '1', '-f', 'rawvideo', '-']);
            $probe->setTimeout(15)->mustRun();
            $pixel = $probe->getOutput();
            return strlen($pixel) >= 3 ? .2126 * ord($pixel[0]) + .7152 * ord($pixel[1]) + .0722 * ord($pixel[2]) : null;
        } catch (\Throwable) { return null; }
    }

    private function gradient(string $path, string $style, int $seed): void
    {
        $palettes = [
            'technology' => [[9, 22, 47], [18, 71, 95]], 'nature' => [[11, 41, 38], [52, 94, 73]],
            'cinematic' => [[29, 31, 51], [99, 57, 55]], 'product' => [[19, 37, 61], [52, 76, 90]],
            'abstract' => [[22, 31, 65], [68, 51, 94]], 'minimal' => [[24, 41, 53], [65, 74, 87]],
        ];
        [$top, $bottom] = $palettes[$style] ?? $palettes['abstract'];
        $width = 540; $height = 960;
        $file = fopen($path, 'wb');
        if (!$file) throw new \RuntimeException('Could not create background frame.');
        try {
            fwrite($file, "P6\n$width $height\n255\n");
            for ($y = 0; $y < $height; $y++) {
                $row = '';
                for ($x = 0; $x < $width; $x++) {
                    $blend = min(1, max(0, $y / $height + 0.13 * sin($x / 125 + $seed)));
                    $glow = max(0, 1 - hypot(($x - $width * .75) / $width, ($y - $height * .27) / $height) * 2) * 27;
                    foreach ([0, 1, 2] as $channel) $row .= chr((int) min(255, $top[$channel] * (1 - $blend) + $bottom[$channel] * $blend + $glow));
                }
                fwrite($file, $row);
            }
        } finally { fclose($file); }
    }

    private function subtitles(array $snapshot, bool $mock, array $darkInk = []): string
    {
        $text = "[Script Info]\nScriptType: v4.00+\nPlayResX: 1080\nPlayResY: 1920\nWrapStyle: 0\n[V4+ Styles]\nFormat: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n";
        $text .= "Style: Captions,DejaVu Sans,48,&H00FFFFFF,&H00FFFFFF,&H00102030,&H70102030,1,0,0,0,100,100,0,0,1,4,1,2,110,110,440,1\n";
        $text .= "Style: Headline,DejaVu Sans,86,&H00FFFFFF,&H00FFFFFF,&H00102030,&H70102030,1,0,0,0,100,100,0,0,1,4,2,8,95,95,290,1\n";
        $text .= "Style: Body,DejaVu Sans,48,&H00FFFFFF,&H00FFFFFF,&H00102030,&H70102030,1,0,0,0,100,100,0,0,1,3,1,8,95,95,550,1\n";
        $text .= "Style: CTA,DejaVu Sans,50,&H00FFFFFF,&H00FFFFFF,&H00102030,&H00163348,1,0,0,0,100,100,0,0,1,3,1,2,190,190,320,1\n";
        $text .= "Style: Badge,DejaVu Sans,38,&H00FFFFFF,&H00FFFFFF,&H00335C64,&H00163348,1,0,0,0,100,100,0,0,3,3,1,8,190,190,155,1\n";
        $text .= "Style: Disclosure,DejaVu Sans,27,&H00FFFFFF,&H00FFFFFF,&H00102030,&H80102030,0,0,0,0,100,100,0,0,1,2,1,2,90,90,95,1\n";
        $text .= "[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";
        $duration = array_sum(array_column($snapshot['scenes'], 'duration'));
        $line = function (float $start, float $end, string $style, string $value, string $effect = ''): string {
            // Neutralize ASS override tags, commas in control fields and line controls from user content.
            $value = str_replace(['\\', '{', '}', "\r", "\n"], ['', '(', ')', '', '\\N'], $value);
            $time = fn ($s) => sprintf('%d:%02d:%05.2f', floor($s / 3600), floor($s / 60) % 60, fmod($s, 60));
            return 'Dialogue: 0,'.$time($start).','.$time($end).",$style,,0,0,0,,".$effect.$value."\n";
        };
        $captionWords = preg_split('/\s+/u', trim($snapshot['captions']));
        $spokenWeights = array_map(fn ($scene) => max(1, count(preg_split('/\s+/u', trim($scene['text'])))), $snapshot['scenes']);
        $remaining = count($captionWords); $totalWeight = array_sum($spokenWeights); $cursor = 0;
        $offset = 0; $lastIndex = count($snapshot['scenes']) - 1;
        foreach ($snapshot['scenes'] as $index => $scene) {
            $end = $offset + $scene['duration'];
            $count = $index === $lastIndex ? $remaining : min($remaining, max(1, (int) round($remaining * $spokenWeights[$index] / max(1, $totalWeight))));
            $words = array_slice($captionWords, $cursor, $count);
            $chunks = array_chunk($words, 6);
            foreach ($chunks as $partIndex => $chunk) {
                $start = $offset + $scene['duration'] * $partIndex / count($chunks);
                $stop = $offset + $scene['duration'] * ($partIndex + 1) / count($chunks);
                $captionInk = ($darkInk[$scene['id']] ?? (($scene['background_type'] ?? '') === 'solid_light')) ? '{\\c&H002A2521&\\3c&H00F4F3EC&\\bord1\\shad0}' : '';
                $text .= $line($start, $stop, 'Captions', implode(' ', $chunk), $captionInk);
            }
            $cursor += $count; $remaining -= $count; $totalWeight -= $spokenWeights[$index];
            // Treat headline, body and CTA as one centered composition. ASS alignment 5
            // anchors a multiline string at its actual center, not its top edge.
            $animation = $scene['animation'] ?? 'fade_up';
            $ink = ($darkInk[$scene['id']] ?? (($scene['background_type'] ?? '') === 'solid_light')) ? '\\1c&H002A2521&\\3c&H00F4F3EC&' : '';
            $headlineInk = isset($scene['headline_color']) ? '\\1c'.$this->assColor($scene['headline_color']).'\\3c'.$this->assOutline($scene['headline_color']) : $ink;
            $bodyInk = isset($scene['body_color']) ? '\\1c'.$this->assColor($scene['body_color']).'\\3c'.$this->assOutline($scene['body_color']) : $ink;
            $type = $this->typographyForScene($scene);
            ['headline' => $headline, 'headline_size' => $headlineSize, 'headline_line_width' => $lineWidth,
                'wrapped_headline' => $wrapped, 'body' => $body, 'body_size' => $bodySize] = $type;
            $template = $scene['template'] ?? ($index === $lastIndex ? 'cta_button' : 'none');
            $label = app(SceneDesignAgent::class)->templateText($template, $snapshot['cta']);
            $badge = in_array($template, ['product_title', 'feature_highlight', 'bullet_point', 'lower_third', 'tag_badge', 'powered_by_dewdora'], true);
            $visibleLabel = $label !== '' ? mb_strimwidth($label, 0, $badge ? 54 : 32, '…') : '';
            $fontWeight = ['regular' => 400, 'medium' => 500, 'semibold' => 600, 'bold' => 700, 'extra_bold' => 800][$scene['text_font_weight'] ?? 'bold'] ?? 1;
            $stroke = ['none' => 0, 'thin' => 1.5, 'medium' => 3, 'thick' => 5][$scene['text_stroke'] ?? 'medium'] ?? 3;
            $shadow = ['none' => 0, 'soft' => 1, 'medium' => 2, 'strong' => 4][$scene['text_shadow'] ?? 'soft'] ?? 1;
            $outline = isset($scene['text_stroke_color']) ? '\\3c'.$this->assColor($scene['text_stroke_color']) : '';
            $style = "\\b$fontWeight\\bord$stroke\\shad$shadow$outline";
            $body = $type['body'];
            $headlineLines = substr_count($wrapped, "\n") + 1;
            $bodyLines = $body !== '' ? substr_count($body, "\n") + 1 : 0;
            $headlineHeight = $headlineLines * $headlineSize * 1.24;
            $bodyHeight = $bodyLines ? $bodyLines * $bodySize * 1.25 : 0;
            $ctaHeight = $visibleLabel !== '' ? 112 : 0;
            $gap = 34;
            $wholeHeight = $headlineHeight + ($bodyHeight ? $bodyHeight + $gap : 0) + ($ctaHeight ? $ctaHeight + $gap : 0);
            // Keep the entire group between y=490 and y=1430 on a 1080x1920 frame.
            $scale = min(1, 900 / max(1, $wholeHeight));
            $headlineSize = max(36, (int) floor($headlineSize * $scale));
            $bodySize = max(26, (int) floor($bodySize * $scale));
            $headlineHeight *= $scale; $bodyHeight *= $scale; $ctaHeight *= $scale; $gap *= $scale;
            $wholeHeight = $headlineHeight + ($bodyHeight ? $bodyHeight + $gap : 0) + ($ctaHeight ? $ctaHeight + $gap : 0);
            $top = 960 - $wholeHeight / 2;
            $headlineY = (int) round($top + $headlineHeight / 2);
            $bodyY = (int) round($top + $headlineHeight + $gap + $bodyHeight / 2);
            $ctaY = (int) round($top + $wholeHeight - $ctaHeight / 2);
            $headlineStart = $offset + min(.2, $scene['duration'] * .06);
            $headlineEnd = $offset + $scene['duration'] * .69;
            $headlineEffect = "\\an5\\fs$headlineSize".$this->entrance($animation, 540, $headlineY)."\\fad(240,180)$headlineInk$style";
            if (in_array($animation, ['typewriter', 'cursor_reveal', 'word_stagger'], true) && mb_strlen($headline) <= 75) {
                $pieces = $animation === 'word_stagger' ? preg_split('/\s+/u', $headline) : preg_split('//u', $headline, -1, PREG_SPLIT_NO_EMPTY);
                $steps = min($animation === 'word_stagger' ? 8 : 16, count($pieces));
                $reveal = min(1.8, $scene['duration'] * .38);
                for ($j = 1; $j <= $steps; $j++) {
                    $visible = (int) ceil(count($pieces) * $j / $steps);
                    $fragment = $animation === 'word_stagger' ? implode(' ', array_slice($pieces, 0, $visible)) : implode('', array_slice($pieces, 0, $visible));
                    $cursorGlyph = $animation === 'cursor_reveal' && $j < $steps && $j % 2 === 0 ? '_' : '';
                    $text .= $line($headlineStart + ($j - 1) * $reveal / $steps, $j === $steps ? $headlineEnd : $headlineStart + $j * $reveal / $steps, 'Headline', $this->wrapOverlay($fragment.$cursorGlyph, $lineWidth, 3), "{\\an5\\pos(540,$headlineY)\\fs$headlineSize$headlineInk$style}");
                }
            } else $text .= $line($headlineStart, $headlineEnd, 'Headline', $wrapped, '{'.$headlineEffect.'}');
            if ($body !== '') {
                $text .= $line($offset + $scene['duration'] * .3, $offset + $scene['duration'] * .88, 'Body', $body, "{\\an5\\pos(540,$bodyY)\\fs$bodySize\\fad(380,180)$bodyInk$style}");
            }
            if ($visibleLabel !== '') {
                $size = (int) round(($badge ? 36 : (mb_strlen($visibleLabel) > 28 ? 42 : 54)) * $scale);
                $ctaStart = $offset + $scene['duration'] * ($badge ? .58 : .72);
                if (!$badge) {
                    $border = $this->assColor($scene['cta_border_color'] ?? '#16805c');
                    $fill = $this->assColor($scene['cta_background_color'] ?? '#101820');
                    $width = min(860, max(300, (int) (mb_strlen($visibleLabel) * $size * .6 + 90)));
                    $left = intdiv(1080 - $width, 2); $buttonTop = (int) round($ctaY - $ctaHeight / 2);
                    $right = $width - 18; $bottom = (int) $ctaHeight;
                    $path = "m 18 0 l $right 0 l $width 18 l $width ".($bottom - 18)." l $right $bottom l 18 $bottom l 0 ".($bottom - 18)." l 0 18";
                    $text .= $line($ctaStart, $end, 'CTA', $path, "{\\an7\\pos($left,$buttonTop)\\p1\\1c$fill\\1a&HC0&\\3c$border\\3a&H00&\\bord4\\shad0\\fad(350,180)}");
                }
                $ctaInk = $badge ? '' : '\\1c'.$this->assColor($scene['cta_text_color'] ?? '#ffffff').'\\3c'.$this->assOutline($scene['cta_text_color'] ?? '#ffffff');
                $text .= $line($ctaStart, $end, $badge ? 'Badge' : 'CTA', $visibleLabel, "{\\an5\\pos(540,$ctaY)\\fs$size\\fad(350,180)$ctaInk$style}");
            }
            $offset = $end;
        }
        $text .= $line(0, $duration, 'Disclosure', $snapshot['disclosure']);
        if ($mock) $text .= $line(0, $duration, 'Badge', 'MOCK · no narration', '{\\an8\\pos(540,80)\\fs24}');
        return $text;
    }

    public function typographyForScene(array $scene): array
    {
        $headline = mb_strimwidth($scene['headline'] ?? $scene['text'], 0, 60, '…');
        $length = mb_strlen($headline);
        $responsiveSize = $length <= 22 ? 90 : ($length <= 40 ? 86 : ($length <= 52 ? 82 : 78));
        $headlineSize = min((int) ($scene['headline_font_size'] ?? $responsiveSize), $responsiveSize + 14);
        $lineWidth = max(14, min(22, (int) floor(1530 / $headlineSize)));
        $bodyText = mb_strimwidth($scene['body'] ?? '', 0, 100, '…');
        $responsiveBody = mb_strlen($bodyText) <= 45 ? 48 : (mb_strlen($bodyText) <= 75 ? 45 : 42);
        $bodySize = min((int) ($scene['body_font_size'] ?? $responsiveBody), mb_strlen($bodyText) <= 45 ? 60 : (mb_strlen($bodyText) <= 75 ? 50 : 44));
        return [
            'headline' => $headline,
            'headline_size' => $headlineSize,
            'headline_line_width' => $lineWidth,
            'wrapped_headline' => $this->wrapOverlay($headline, $lineWidth, 3),
            'body' => $this->wrapOverlay($bodyText, min(32, (int) floor(1460 / $bodySize)), 3),
            'body_size' => $bodySize,
        ];
    }

    private function assColor(string $hex): string
    {
        return '&H00'.strtoupper(substr($hex, 5, 2).substr($hex, 3, 2).substr($hex, 1, 2)).'&';
    }

    private function assOutline(string $hex): string
    {
        [$r, $g, $b] = array_map('hexdec', [substr($hex, 1, 2), substr($hex, 3, 2), substr($hex, 5, 2)]);
        return $r * .2126 + $g * .7152 + $b * .0722 > 145 ? '&H00201810&' : '&H00F4F3EC&';
    }

    private function wrapOverlay(string $value, int $limit, int $maxLines): string
    {
        $words = preg_split('/\s+/u', trim($value));
        $lines = []; $current = '';
        foreach ($words as $word) {
            while (mb_strlen($word) > $limit) {
                if ($current !== '') { $lines[] = $current; $current = ''; }
                $lines[] = mb_substr($word, 0, $limit);
                $word = mb_substr($word, $limit);
            }
            if (mb_strlen($current.' '.$word) > $limit && $current !== '') { $lines[] = $current; $current = ''; }
            $current = trim($current.' '.$word);
        }
        if ($current !== '') $lines[] = $current;
        if (count($lines) > $maxLines) $lines[$maxLines - 1] = rtrim(mb_substr($lines[$maxLines - 1], 0, $limit - 1)).'…';
        return implode("\n", array_slice($lines, 0, $maxLines));
    }

    private function entrance(string $kind, int $x, int $y): string
    {
        return match ($kind) {
            'slide_in', 'slide_left' => "\\move(".($x - 80).",$y,$x,$y,0,600)",
            'slide_right' => "\\move(".($x + 80).",$y,$x,$y,0,600)",
            'slide_up', 'fade_up', 'staggered' => "\\move($x,".($y + 45).",$x,$y,0,600)",
            'scale_in', 'scale_pop' => "\\pos($x,$y)\\fscx82\\fscy82\\t(0,550,\\fscx100\\fscy100)",
            'masked_reveal' => "\\pos($x,$y)\\blur5\\t(0,550,\\blur0)",
            default => "\\pos($x,$y)",
        };
    }
}
