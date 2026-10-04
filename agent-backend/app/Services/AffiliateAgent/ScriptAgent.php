<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Usage;
use Illuminate\Support\Facades\Http;

class ScriptAgent
{
    public function draft(string $idea, array $research, int $seconds, int $contentId): array
    {
        if (config('affiliate_agent.provider') === 'mock') {
            $text = 'Explore '.$research['product'].' using only approved source information. Visit the official product page for details.';
            return ['script' => $text, 'scenes' => [['id' => 'intro', 'text' => $research['product'], 'duration' => $seconds / 2, 'media_id' => null], ['id' => 'details', 'text' => 'Explore official details', 'duration' => $seconds / 2, 'media_id' => null]], 'captions' => $text, 'cta' => 'Explore the product', 'disclosure' => 'Affiliate link: I may earn a commission.'];
        }
        if (config('affiliate_agent.provider') !== 'openai' || !config('affiliate_agent.openai_key')) throw new \RuntimeException('Configure OpenAI before production generation.');
        $start = microtime(true);
        $response = Http::withToken(config('affiliate_agent.openai_key'))->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
            'model' => config('affiliate_agent.model'), 'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => 'Draft factual affiliate short video from approved source excerpts only. Return JSON object with script, scenes (id,text,duration,media_id:null), captions, cta, disclosure. Scene durations sum to requested seconds. Do not invent product claims. Include an affiliate disclosure. No markdown.'],
                ['role' => 'user', 'content' => json_encode(compact('idea', 'research', 'seconds'), JSON_THROW_ON_ERROR)],
            ],
        ])->throw()->json();
        $usage = $response['usage'] ?? [];
        Usage::create(['content_id' => $contentId, 'provider' => 'openai', 'operation' => 'script', 'model' => config('affiliate_agent.model'), 'input_tokens' => $usage['prompt_tokens'] ?? 0, 'output_tokens' => $usage['completion_tokens'] ?? 0, 'duration_seconds' => microtime(true) - $start]);
        return json_decode($response['choices'][0]['message']['content'] ?? '', true, 512, JSON_THROW_ON_ERROR);
    }
}
