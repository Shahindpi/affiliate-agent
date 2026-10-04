<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Usage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VideoComposerAgent
{
    public function render(array $snapshot, array $artifacts, int $contentId): array
    {
        // Protect the VPS even if an extra worker is accidentally started.
        return Cache::lock('affiliate-agent:ffmpeg', 900)->block(10, function () use ($snapshot, $artifacts, $contentId) {
            $start = microtime(true);
            $tmp = storage_path('app/agent-tmp/'.Str::uuid());
            mkdir($tmp, 0755, true);
            try {
                $duration = array_sum(array_column($snapshot['scenes'], 'duration'));
                $voice = app(AssetStore::class)->absolute($artifacts['voice']);
                $probe = new Process([config('affiliate_agent.ffprobe'), '-v', 'error', '-show_entries', 'format=duration', '-of', 'json', $voice]);
                $probe->setTimeout(30)->mustRun();
                $voiceDuration = (float) (json_decode($probe->getOutput(), true)['format']['duration'] ?? 0);
                if ($voiceDuration <= 0 || $voiceDuration > $duration + 0.5) throw new \RuntimeException('Voiceover exceeds the scene duration. Shorten the script or extend scenes within 45 seconds.');
                $command = [config('affiliate_agent.ffmpeg'), '-y'];
                $filters = [];
                foreach ($snapshot['scenes'] as $index => $scene) {
                    $asset = $artifacts['scenes'][$scene['id']] ?? null;
                    if ($asset) {
                        $command = [...$command, ...($asset['mime_type'] === 'video/mp4' ? ['-stream_loop', '-1'] : ['-loop', '1']), '-i', app(AssetStore::class)->absolute($asset)];
                    } else array_push($command, '-f', 'lavfi', '-i', 'color=c=0x14232e:s=1080x1920:r=30');
                    $filters[] = "[$index:v]scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=30,trim=duration={$scene['duration']},setpts=PTS-STARTPTS[v$index]";
                }
                $n = count($snapshot['scenes']);
                array_push($command, '-i', $voice);
                file_put_contents($tmp.'/captions.ass', $this->subtitles($snapshot, !empty($artifacts['voice']['mock'])));
                $inputs = implode('', array_map(fn ($i) => "[v$i]", range(0, $n - 1)));
                $filters[] = "$inputs concat=n=$n:v=1:a=0,subtitles='$tmp/captions.ass'[out]";
                $filters[] = "[$n:a]apad,atrim=duration={$duration}[aout]";
                array_push($command, '-filter_complex_threads', '1', '-filter_complex', implode(';', $filters), '-map', '[out]', '-map', '[aout]', '-t', (string) $duration, '-c:v', 'libx264', '-threads', '2', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', $tmp.'/master.mp4');
                $p = new Process($command);
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

    private function subtitles(array $snapshot, bool $mock): string
    {
        $text = "[Script Info]\nScriptType: v4.00+\nPlayResX: 1080\nPlayResY: 1920\nWrapStyle: 0\n[V4+ Styles]\nFormat: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\nStyle: Default,DejaVu Sans,48,&H00FFFFFF,&H00FFFFFF,&H00102030,&H80102030,1,0,0,0,100,100,0,0,1,3,1,2,80,80,460,1\nStyle: Scene,DejaVu Sans,54,&H00FFFFFF,&H00FFFFFF,&H00102030,&H80102030,1,0,0,0,100,100,0,0,1,3,1,8,90,90,230,1\nStyle: CTA,DejaVu Sans,40,&H00FFFFFF,&H00FFFFFF,&H00102030,&H80102030,1,0,0,0,100,100,0,0,1,3,1,2,80,80,290,1\nStyle: Disclosure,DejaVu Sans,30,&H00FFFFFF,&H00FFFFFF,&H00102030,&H80102030,0,0,0,0,100,100,0,0,1,2,1,2,80,80,170,1\n[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";
        $duration = array_sum(array_column($snapshot['scenes'], 'duration'));
        $line = function ($start, $end, $style, $value) {
            // Neutralize ASS override tags and controls from user/provider content.
            $value = str_replace(['\\', '{', '}', "\r", "\n"], ['', '(', ')', '', '\\N'], $value);
            $time = fn ($s) => sprintf('%d:%02d:%05.2f', floor($s / 3600), floor($s / 60) % 60, fmod($s, 60));
            return 'Dialogue: 0,'.$time($start).','.$time($end).",$style,,0,0,0,,".$value."\n";
        };
        $chunks = array_chunk(preg_split('/\s+/u', trim($snapshot['captions'])), 6);
        $part = $duration / max(1, count($chunks));
        foreach ($chunks as $i => $words) $text .= $line($i * $part, ($i + 1) * $part, 'Default', implode(' ', $words));
        $offset = 0;
        foreach ($snapshot['scenes'] as $scene) {
            $text .= $line($offset, $offset + $scene['duration'], 'Scene', $scene['text']);
            $offset += $scene['duration'];
        }
        $text .= $line(max(0, $duration - 6), $duration, 'CTA', $snapshot['cta']);
        $text .= $line(0, $duration, 'Disclosure', $snapshot['disclosure']);
        if ($mock) $text .= $line(0, $duration, 'CTA', 'MOCK PREVIEW — test tone, no narration');
        return $text;
    }
}
