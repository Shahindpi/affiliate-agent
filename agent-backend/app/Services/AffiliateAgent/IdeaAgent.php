<?php

namespace App\Services\AffiliateAgent;

class IdeaAgent
{
    public function propose(array $plan): string
    {
        $product = $plan['product'];
        $type = ucfirst(str_replace('_', ' ', $plan['type']));
        $campaign = $plan['campaign'] ?? null;
        $candidates = $campaign ? ["$type: {$campaign->name} with {$product->name}"] : [];
        array_push($candidates, "$type: getting started with {$product->name}", "$type: common {$product->name} workflow", "$type: one useful {$product->name} feature", "$type: {$product->name} step by step");
        foreach (range(2, 20) as $n) $candidates[] = "$type: {$product->name} walkthrough $n";
        foreach ($candidates as $candidate) {
            $similarity = max(array_map(fn ($old) => $this->similarity($candidate, $old), $plan['recent_topics']) ?: [0]);
            if ($similarity < 0.8) return $candidate;
        }
        throw new \RuntimeException('Recent content is too similar; add more approved product topics.');
    }

    private function similarity(string $a, string $b): float
    {
        $words = fn ($s) => array_unique(array_filter(preg_split('/[^\pL\pN]+/u', mb_strtolower($s)), fn ($w) => mb_strlen($w) > 2));
        $left = $words($a); $right = $words($b);
        return count(array_intersect($left, $right)) / max(1, count(array_unique([...$left, ...$right])));
    }
}
