<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Source;
use App\Models\Agent\SourceDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SourceSyncService
{
    public function checkUrl(Source $source): string
    {
        $url = $source->url;
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') throw ValidationException::withMessages(['url' => 'Use a trusted HTTPS URL.']);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!$host || filter_var($host, FILTER_VALIDATE_IP) || !in_array($host, $source->allowed_domains ?? [], true)) throw ValidationException::withMessages(['allowed_domains' => 'The source host must be explicitly allowed.']);
        $ips = gethostbynamel($host) ?: [];
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw ValidationException::withMessages(['url' => 'Source host resolves to a private or reserved address.']);
        }
        if (!$ips) throw ValidationException::withMessages(['url' => 'Source host cannot be resolved.']);
        return $ips[0];
    }

    public function sync(Source $source): void
    {
        $run = $source->runs()->create(['status' => 'RUNNING', 'started_at' => now()]);
        try {
            if (!in_array($source->type, ['MANUAL', 'OFFICIAL_WEBSITE', 'OFFICIAL_DOCS', 'RSS_OR_FEED', 'API'], true)) throw new \RuntimeException('Use the dedicated CSV import action for CSV sources.');
            if ($source->type !== 'MANUAL') {
                $ip = $this->checkUrl($source);
                $url = $source->url;
                $host = parse_url($url, PHP_URL_HOST);
                // Pin DNS for the request after validating the address to avoid rebinding.
                $pin = ['curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ip]]];
                // Robots applies to website and documentation pages; no automatic link traversal.
                if (in_array($source->type, ['OFFICIAL_WEBSITE', 'OFFICIAL_DOCS'], true)) {
                    $robots = Http::withOptions($pin)->timeout(10)->withoutRedirecting()->get('https://'.$host.'/robots.txt');
                    if ($robots->successful() && $this->disallowed($robots->body(), parse_url($url, PHP_URL_PATH) ?: '/')) throw new \RuntimeException('robots.txt disallows this path.');
                }
                $request = Http::withOptions($pin)->timeout(20)->withoutRedirecting()->withHeaders(['User-Agent' => 'DewdoraAffiliateAgent/1.0 (+admin-configured source)', 'Accept' => 'text/html,application/json,application/rss+xml,application/xml,text/plain']);
                if ($source->credentials) $request = $request->withToken($source->credentials);
                $response = $request->get($url);
                if (!$response->successful()) throw new \RuntimeException('Source returned HTTP '.$response->status().'. Redirects are not followed.');
                $body = mb_substr($response->body(), 0, 200000);
                $body = trim(html_entity_decode(strip_tags(preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $body))));
            } else $body = trim($source->notes ?? '');
            if (!$body) throw new \RuntimeException('Source returned no factual content.');
            $hash = hash('sha256', $body);
            $existing = $source->documents()->where('sha256', $hash)->first();
            if (!$existing) $source->documents()->create(['source_url' => $source->url, 'title' => $source->name, 'body' => $body, 'sha256' => $hash, 'status' => 'PENDING', 'synced_at' => now()]);
            $source->update(['status' => 'SYNCED', 'last_error' => null, 'last_synced_at' => now(), 'next_sync_at' => now()->addHours($source->frequency_hours)]);
            $run->update(['status' => 'COMPLETED', 'documents' => $existing ? 0 : 1, 'finished_at' => now()]);
        } catch (\Throwable $e) {
            $source->update(['status' => 'ERROR', 'last_error' => mb_substr($e->getMessage(), 0, 2000), 'next_sync_at' => now()->addHours($source->frequency_hours)]);
            $run->update(['status' => 'FAILED', 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
            throw $e;
        }
    }

    private function disallowed(string $robots, string $path): bool
    {
        $active = false;
        foreach (preg_split('/\R/', $robots) as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if (stripos($line, 'User-agent:') === 0) $active = trim(substr($line, 11)) === '*';
            if ($active && stripos($line, 'Disallow:') === 0) {
                $rule = trim(substr($line, 9));
                if ($rule !== '' && str_starts_with($path, $rule)) return true;
            }
        }
        return false;
    }

    public function approve(SourceDocument $document): void { $document->update(['status' => 'APPROVED', 'approved_at' => now()]); }

    public function csv(Source $source, string $csv): int
    {
        if ($source->type !== 'CSV_IMPORT') throw ValidationException::withMessages(['file' => 'Choose a CSV_IMPORT source.']);
        $lines = preg_split('/\R/', $csv);
        $header = str_getcsv(array_shift($lines) ?? '');
        if (!in_array('title', $header, true) || !in_array('body', $header, true)) throw ValidationException::withMessages(['file' => 'CSV needs title and body columns; optional source_url.']);
        $count = 0;
        foreach (array_slice($lines, 0, 200) as $line) {
            if (!trim($line)) continue;
            $columns = str_getcsv($line);
            if (count($columns) !== count($header)) continue;
            $row = array_combine($header, $columns);
            if (!$row || !trim($row['body'] ?? '')) continue;
            $body = mb_substr(trim($row['body']), 0, 20000);
            $source->documents()->create(['title' => mb_substr($row['title'] ?: $source->name, 0, 255), 'body' => $body, 'source_url' => $row['source_url'] ?? null, 'sha256' => hash('sha256', $body), 'status' => 'PENDING', 'synced_at' => now()]);
            $count++;
        }
        $source->update(['status' => 'SYNCED', 'last_synced_at' => now(), 'last_error' => null]);
        return $count;
    }
}
