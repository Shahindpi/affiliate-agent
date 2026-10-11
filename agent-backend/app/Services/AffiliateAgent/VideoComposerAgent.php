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
                if ($voiceDuration > $duration) {
                    if (in_array('scenes', $locks, true) || collect($locks)->contains(fn ($lock) => str_starts_with($lock, 'scenes.'))) {
                        throw new \RuntimeException('Narration needs longer scenes, but scene timing is locked.');
                    }
                    $target = min(45, max($duration, $voiceDuration + 0.15));
                    $elapsed = 0;
                    $last = count($snapshot['scenes']) - 1;
                    foreach ($snapshot['scenes'] as $i => &$scene) {
                        $scene['duration'] = $i === $last ? round($target - $elapsed, 3) : round($scene['duration'] * $target / $duration, 3);
                        $elapsed += $scene['duration'];
                    }
                    unset($scene);
                    $duration = $target;
                }
                $command = [config('affiliate_agent.ffmpeg'), '-y'];
                $filters = [];
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
                    $motion = $solid ? 'scale=1080:1920' : "scale=1200:2134:force_original_aspect_ratio=increase,crop=1080:1920:x='(in_w-out_w)/2+24*sin(n/60)':y='(in_h-out_h)/2+20*cos(n/75)'";
                    $shade = $solid && $scene['background_type'] === 'solid_light' ? '' : ',drawbox=x=0:y=0:w=iw:h=ih:color=black@0.25:t=fill';
                    $filters[] = '['.$index.':v]'.$motion.',setsar=1,fps=30,trim=duration='.$scene['duration'].',setpts=PTS-STARTPTS'.$shade.'[v'.$index.']';
                }
                
                $n = count($snapshot['scenes']);

                array_push($command, '-i', $voice);

                $captionsPath = $tmp.'/captions.ass';

                file_put_contents(
                    $captionsPath,
                    $this->subtitles(
                        $snapshot,
                        !empty($artifacts['voice']['mock'])
                    )
                );

                $inputs = implode(
                    '',
                    array_map(fn ($i) => "[v$i]", range(0, $n - 1))
                );

                $captionFilterPath = $this->ffmpegFilterPath(
                    basename($captionsPath)
                );

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

    private function subtitles(array $snapshot, bool $mock): string
    {
        $text = "[Script Info]\nScriptType: v4.00+\nPlayResX: 1080\nPlayResY: 1920\nWrapStyle: 0\n[V4+ Styles]\nFormat: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n";
        $text .= "Style: Captions,DejaVu Sans,48,&H00FFFFFF,&H00FFFFFF,&H00102030,&H70102030,1,0,0,0,100,100,0,0,1,3,1,2,70,70,450,1\n";
        $text .= "Style: Headline,DejaVu Sans,82,&H00FFFFFF,&H00FFFFFF,&H00102030,&H70102030,1,0,0,0,100,100,0,0,1,4,2,8,85,85,290,1\n";
        $text .= "Style: Body,DejaVu Sans,47,&H00FFFFFF,&H00FFFFFF,&H00102030,&H70102030,0,0,0,0,100,100,0,0,1,3,1,8,95,95,550,1\n";
        $text .= "Style: CTA,DejaVu Sans,50,&H00FFFFFF,&H00FFFFFF,&H00335C64,&H00163348,1,0,0,0,100,100,0,0,3,4,1,2,190,190,320,1\n";
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
        $chunks = array_chunk(preg_split('/\s+/u', trim($snapshot['captions'])), 6);
        $part = $duration / max(1, count($chunks));
        foreach ($chunks as $i => $words) $text .= $line($i * $part, ($i + 1) * $part, 'Captions', implode(' ', $words));
        $offset = 0; $lastIndex = count($snapshot['scenes']) - 1;
        foreach ($snapshot['scenes'] as $index => $scene) {
            $end = $offset + $scene['duration'];
            $layout = $scene['layout'] ?? 'center';
            $x = $layout === 'center' ? 540 : 95;
            $align = $layout === 'center' ? 8 : 7;
            $y = $layout === 'split' ? 380 : 290;
            $animation = $scene['animation'] ?? 'fade_up';
            $enter = match ($animation) {
                'slide_in' => "\\move(".($x - 60).",$y,$x,$y,0,500)",
                'scale_in' => '\\fscx90\\fscy90\\t(0,500,\\fscx100\\fscy100)',
                'masked_reveal' => '\\blur3\\t(0,450,\\blur0)',
                'staggered' => "\\move($x,".($y + 30).",$x,$y,0,500)",
                default => "\\move($x,".($y + 28).",$x,$y,0,500)",
            };
            $ink = ($scene['background_type'] ?? '') === 'solid_light' ? '\\c&H002A2521&\\3c&H00F4F3EC&' : '';
            $headline = mb_strimwidth($scene['headline'] ?? $scene['text'], 0, 55, '…');
            $body = mb_strimwidth($scene['body'] ?? '', 0, 180, '…');
            $effect = "{\\an$align$enter\\fad(280,200)$ink}";
            $text .= $line($offset, $end, 'Headline', $headline, $effect);
            if ($body !== '') $text .= $line(min($end - .1, $offset + .35), $end, 'Body', $body, "{\\an$align\\pos($x,".($y + 230).")\\fad(450,200)$ink}");
            $template = $scene['template'] ?? ($index === $lastIndex ? 'cta_button' : 'none');
            $label = app(SceneDesignAgent::class)->templateText($template, $snapshot['cta']);
            if ($label !== '') $text .= $line($offset + min(.2, $scene['duration'] / 4), $end, in_array($template, ['product_title', 'feature_highlight', 'bullet_point', 'lower_third', 'tag_badge', 'powered_by_dewdora'], true) ? 'Badge' : 'CTA', $label, '{\\fad(500,250)\\fscx94\\fscy94\\t(0,450,\\fscx100\\fscy100)}');
            $offset = $end;
        }
        $text .= $line(0, $duration, 'Disclosure', $snapshot['disclosure']);
        if ($mock) $text .= $line(0, $duration, 'Badge', 'MOCK PREVIEW — test tone, no narration');
        return $text;
    }
}
