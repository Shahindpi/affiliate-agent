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
            return ['script' => $text, 'scenes' => [
                ['id' => 'hook', 'text' => $research['product'], 'duration' => $seconds * 0.24, 'media_id' => null, 'purpose' => 'hook'],
                ['id' => 'benefit', 'text' => 'Explore verified benefits', 'duration' => $seconds * 0.28, 'media_id' => null, 'purpose' => 'benefit'],
                ['id' => 'demo', 'text' => 'See the product workflow', 'duration' => $seconds * 0.28, 'media_id' => null, 'purpose' => 'demo'],
                ['id' => 'closing', 'text' => 'Explore official details', 'duration' => $seconds * 0.20, 'media_id' => null, 'purpose' => 'closing'],
            ], 'captions' => $text, 'cta' => 'Explore the product', 'disclosure' => 'Affiliate link: I may earn a commission.'];
        }
        if (config('affiliate_agent.provider') !== 'openai' || !config('affiliate_agent.openai_key')) throw new \RuntimeException('Configure OpenAI before production generation.');
        $start = microtime(true);
        $response = Http::withToken(config('affiliate_agent.openai_key'))->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
            'model' => config('affiliate_agent.model'), 'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => 'Draft a factual short-form affiliate reel from approved source excerpts only. Return JSON object with script, scenes (id,text,duration,media_id:null,purpose), captions, cta, disclosure. Plan a brief hook, problem or benefit, concrete product demo/workflow and closing CTA; vary scene count when appropriate (3–5), avoid repeating claims. Scene purpose is one of hook,problem,benefit,demo,closing. Scene durations sum to requested seconds. Scene text is concise on-screen copy. Do not invent product claims. Include an affiliate disclosure. No markdown.'],
                ['role' => 'user', 'content' => json_encode(compact('idea', 'research', 'seconds'), JSON_THROW_ON_ERROR)],
            ],
        ])->throw()->json();
        $usage = $response['usage'] ?? [];
        Usage::create(['content_id' => $contentId, 'provider' => 'openai', 'operation' => 'script', 'model' => config('affiliate_agent.model'), 'input_tokens' => $usage['prompt_tokens'] ?? 0, 'output_tokens' => $usage['completion_tokens'] ?? 0, 'duration_seconds' => microtime(true) - $start]);
        return json_decode($response['choices'][0]['message']['content'] ?? '', true, 512, JSON_THROW_ON_ERROR);
    }
}
