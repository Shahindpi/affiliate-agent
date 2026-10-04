<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Usage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VoiceAgent
{
    public function generate(array $snapshot, int $contentId): array
    {
        $start = microtime(true);
        $mock = config('affiliate_agent.voice_provider') === 'mock';
        if ($mock) {
            $tmp = storage_path('app/agent-tmp/'.Str::uuid().'.wav');
            if (!is_dir(dirname($tmp))) mkdir(dirname($tmp), 0755, true);
            try {
                $seconds = array_sum(array_column($snapshot['scenes'], 'duration'));
                // Audible test tone, explicitly labelled. Never pretend this is narration.
                $p = new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'sine=frequency=220:sample_rate=44100', '-t', (string) $seconds, '-af', 'volume=0.05', $tmp]);
                $p->setTimeout(60)->mustRun();
                $asset = app(AssetStore::class)->put(file_get_contents($tmp), 'wav', 'audio/wav', true);
            } finally { if (is_file($tmp)) unlink($tmp); }
        } else {
            if (config('affiliate_agent.voice_provider') !== 'elevenlabs') throw new \RuntimeException('Unsupported voice provider.');
            $voiceId = $snapshot['voice']['voice_id'] ?: \App\Models\Agent\Setting::current()->default_voice_id ?: config('affiliate_agent.voice_id');
            if (!$voiceId || !config('affiliate_agent.elevenlabs_key') || !preg_match('/^[\w-]+$/', $voiceId)) throw new \RuntimeException('ElevenLabs voice configuration is missing or invalid.');
            $audio = Http::withHeaders(['xi-api-key' => config('affiliate_agent.elevenlabs_key'), 'Accept' => 'audio/mpeg'])->timeout(120)->post('https://api.elevenlabs.io/v1/text-to-speech/'.$voiceId, ['text' => $snapshot['script'], 'model_id' => config('affiliate_agent.voice_model')])->throw()->body();
            $asset = app(AssetStore::class)->put($audio, 'mp3', 'audio/mpeg');
        }
        Usage::create(['content_id' => $contentId, 'provider' => $mock ? 'mock' : 'elevenlabs', 'operation' => 'voice', 'characters' => mb_strlen($snapshot['script']), 'duration_seconds' => microtime(true) - $start, 'mock' => $mock]);
        return $asset;
    }
}
