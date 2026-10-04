<?php

namespace App\Services\AffiliateAgent;

use Illuminate\Validation\ValidationException;

class RevisionPlanner
{
    public function changes(array $before, array $after): array
    {
        $changes = [];
        foreach (ContentSchema::COMPONENTS as $component) {
            if ($component === 'video') continue;
            if (($before[$component] ?? null) === ($after[$component] ?? null)) continue;
            if ($component === 'scenes') {
                $a = array_column($before['scenes'], null, 'id');
                $b = array_column($after['scenes'], null, 'id');
                foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $id) {
                    if (($a[$id] ?? null) !== ($b[$id] ?? null)) $changes[] = ['component' => "scenes.$id", 'before' => $a[$id] ?? null, 'after' => $b[$id] ?? null];
                }
                if (array_keys($a) !== array_keys($b)) $changes[] = ['component' => 'scenes', 'before' => array_keys($a), 'after' => array_keys($b)];
            } elseif ($component === 'metadata') {
                foreach (ContentSchema::PLATFORMS as $p) if ($before['metadata'][$p] !== $after['metadata'][$p]) $changes[] = ['component' => "metadata.$p", 'before' => $before['metadata'][$p], 'after' => $after['metadata'][$p]];
            } else $changes[] = ['component' => $component, 'before' => $before[$component], 'after' => $after[$component]];
        }
        return $changes;
    }

    public function plan(array $before, array $after, array $locks): array
    {
        // Script is the narration source and captions must be synchronized with it.
        if ($before['script'] !== $after['script'] && $before['captions'] === $after['captions']) $after['captions'] = $after['script'];
        $changes = $this->changes($before, $after);
        if (!$changes) throw ValidationException::withMessages(['feedback' => 'This revision contains no changes.']);
        foreach ($changes as $change) foreach ($locks as $lock) {
            if ($this->overlaps($change['component'], $lock)) throw ValidationException::withMessages(['locks' => "The revision would change locked component $lock. Unlock it explicitly first."]);
        }
        $components = array_unique(array_map(fn ($c) => explode('.', $c['component'])[0], $changes));
        $voice = count(array_intersect($components, ['script', 'voice'])) > 0;
        $render = $voice || count(array_intersect($components, ['scenes', 'captions', 'cta', 'disclosure'])) > 0;
        if ($voice && in_array('voice', $locks, true)) throw ValidationException::withMessages(['locks' => 'The script change needs a new voiceover, but voice is locked.']);
        if ($render && in_array('video', $locks, true)) throw ValidationException::withMessages(['locks' => 'This change needs a new render, but video is locked.']);
        return ['snapshot' => $after, 'changes' => $changes, 'steps' => array_values(array_filter([$voice ? 'voice' : null, $render ? 'render' : null, 'qa']))];
    }

    private function overlaps(string $a, string $b): bool
    {
        return $a === $b || str_starts_with($a, $b.'.') || str_starts_with($b, $a.'.');
    }
}
