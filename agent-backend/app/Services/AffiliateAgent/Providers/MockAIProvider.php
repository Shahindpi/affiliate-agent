<?php

namespace App\Services\AffiliateAgent\Providers;

use Illuminate\Validation\ValidationException;

class MockAIProvider implements AIProviderInterface
{
    public function revise(array $snapshot, string $feedback, string $target, array $locks, int $contentId): array
    {
        // Deterministic fixture syntax; do not imply semantic AI understanding in mock mode.
        if (!preg_match('/^set\s+(script|captions|cta|disclosure|scenes\.[\w-]+\.text|metadata\.(?:pinterest|instagram|tiktok|facebook|youtube)\.(?:title|caption|description|cta|disclosure))\s*:\s*(.+)$/is', trim($feedback), $m)) {
            throw ValidationException::withMessages(['feedback' => 'Mock mode: use "set cta: Try the free demo", "set scenes.intro.text: New text", or submit direct component edits. Configure OpenAI for natural-language revisions.']);
        }
        $path = $m[1];
        if ($target !== 'all' && $path !== $target && !str_starts_with($path, $target.'.')) throw ValidationException::withMessages(['feedback' => 'Feedback does not match the selected target.']);
        $component = explode('.', $path)[0];
        if ($component === 'scenes') {
            [, $id] = explode('.', $path);
            foreach ($snapshot['scenes'] as &$scene) if ($scene['id'] === $id) $scene['text'] = trim($m[2]);
        } else data_set($snapshot, $path, trim($m[2]));
        return [$component => $snapshot[$component]];
    }
}
