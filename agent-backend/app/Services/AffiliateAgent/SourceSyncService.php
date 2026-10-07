<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Source;
use App\Models\Agent\SourceDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SourceSyncService
{
    public function checkUrl(Source $source): string
    {
        $url = $source->url;
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') throw ValidationException::withMessages(['url' => 'Use a trusted HTTPS URL.']);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!$host || filter_var($host, FILTER_VALIDATE_IP) || !in_array($host, $source->allowed_domains ?? [], true)) throw ValidationException::withMessages(['allowed_domains' => 'The source host must be explicitly allowed.']);
        return $this->publicIpForHost($host);
    }

    protected function publicIpForHost(string $host): string
    {
        $ips = gethostbynamel($host) ?: [];
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw ValidationException::withMessages(['url' => 'Source host resolves to a private or reserved address.']);
        }
        if (!$ips) throw ValidationException::withMessages(['url' => 'Source host cannot be resolved.']);
        return $ips[0];
    }

    /** Test fetches and parses without creating a document or changing sync state. */
    public function test(Source $source): array
    {
        $httpStatus = null;
        try {
            if ($source->type === 'MANUAL') $this->extract($source->notes ?? '');
            else {
                if (!in_array($source->type, ['OFFICIAL_WEBSITE', 'OFFICIAL_DOCS', 'RSS_OR_FEED', 'API'], true)) throw new \RuntimeException('Use the CSV import or webhook action for this source type.');
                $this->extract($this->fetch($source, $httpStatus));
            }
            $source->update(['last_tested_at' => now(), 'last_test_status' => 'PASSED', 'last_test_error' => null, 'last_http_status' => $httpStatus]);
            return ['ok' => true, 'message' => 'Source fetch and extraction passed.', 'source' => $source->fresh()];
        } catch (\Throwable $e) {
            $source->update(['last_tested_at' => now(), 'last_test_status' => 'FAILED', 'last_test_error' => mb_substr($e->getMessage(), 0, 2000), 'last_http_status' => $httpStatus]);
            return ['ok' => false, 'message' => $source->last_test_error, 'source' => $source->fresh()];
        }
    }

    private function fetch(Source $source, ?int &$httpStatus): string
    {
        $ip = $this->checkUrl($source);
        $url = $source->url;
        $host = parse_url($url, PHP_URL_HOST);
        // Keep the request on the validated public IP; do not follow redirects.
        $pin = ['curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ip]]];
        if (in_array($source->type, ['OFFICIAL_WEBSITE', 'OFFICIAL_DOCS'], true)) {
            $robots = Http::withOptions($pin)->timeout(10)->withoutRedirecting()->get('https://'.$host.'/robots.txt');
            if ($robots->successful() && $this->disallowed($robots->body(), parse_url($url, PHP_URL_PATH) ?: '/')) throw new \RuntimeException('robots.txt disallows this path.');
        }
        $request = Http::withOptions($pin)->timeout(20)->withoutRedirecting()->withHeaders(['User-Agent' => 'DewdoraAffiliateAgent/1.0 (+admin-configured source)', 'Accept' => 'text/html,application/json,application/rss+xml,application/xml,text/plain']);
        if ($source->credentials) $request = $request->withToken($source->credentials);
        $response = $request->get($url);
        $httpStatus = $response->status();
        if (!$response->successful()) throw new \RuntimeException('Source returned HTTP '.$httpStatus.'. Redirects are not followed.');
        return $response->body();
    }

    private function extract(string $raw): string
    {
        $body = mb_substr($raw, 0, 200000);
        $body = trim(html_entity_decode(strip_tags(preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $body))));
        if (!$body) throw new \RuntimeException('Source returned no factual content after extraction.');
        return $body;
    }

    public function sync(Source $source): void
    {
        // Atomic database claim guards manual requests and scheduled workers across processes.
        $claimed = Source::whereKey($source->id)->where('enabled', true)
            ->where(fn ($q) => $q->where('status', '!=', 'SYNCING')->orWhere('sync_started_at', '<', now()->subMinutes(3)))
            ->update(['status' => 'SYNCING', 'sync_started_at' => now(), 'last_error' => null]);
        if (!$claimed) throw ValidationException::withMessages(['source' => 'Source is disabled or already syncing.']);
        $source->refresh();
        $source->runs()->where('status', 'RUNNING')->update(['status' => 'FAILED', 'error' => 'Previous sync was interrupted.', 'finished_at' => now()]);
        $run = null; $httpStatus = null;
        try {
            $run = $source->runs()->create(['status' => 'RUNNING', 'started_at' => now(), 'metadata' => ['type' => $source->type, 'url' => $source->url]]);
            if (!in_array($source->type, ['MANUAL', 'OFFICIAL_WEBSITE', 'OFFICIAL_DOCS', 'RSS_OR_FEED', 'API'], true)) throw new \RuntimeException('Use the dedicated CSV import action for CSV sources.');
            $body = $this->extract($source->type === 'MANUAL' ? ($source->notes ?? '') : $this->fetch($source, $httpStatus));
            $hash = hash('sha256', $body);
            DB::transaction(function () use ($source, $run, $body, $hash, $httpStatus) {
                $existing = $source->documents()->where('sha256', $hash)->first();
                if (!$existing) $source->documents()->create(['source_url' => $source->url, 'title' => $source->name, 'body' => $body, 'sha256' => $hash, 'status' => 'PENDING', 'synced_at' => now()]);
                // Finish only after the extracted snapshot and the run record are persisted.
                $run->update(['status' => 'COMPLETED', 'documents' => $existing ? 0 : 1, 'extracted_items' => 1, 'content_changed' => !$existing, 'http_status' => $httpStatus, 'finished_at' => now()]);
                $source->update(['status' => 'SYNCED', 'last_error' => null, 'last_synced_at' => now(), 'sync_started_at' => null, 'last_http_status' => $httpStatus,
                    'next_sync_at' => $source->type === 'MANUAL' ? null : now()->addHours($source->frequency_hours)]);
            });
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 2000);
            $source->update(['status' => 'SYNC_FAILED', 'last_error' => $message, 'last_sync_failed_at' => now(), 'sync_started_at' => null, 'last_http_status' => $httpStatus,
                'next_sync_at' => $source->type === 'MANUAL' ? null : now()->addHours($source->frequency_hours)]);
            if ($run) $run->update(['status' => 'FAILED', 'error' => $message, 'http_status' => $httpStatus, 'finished_at' => now()]);
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
        $claimed = Source::whereKey($source->id)->where('enabled', true)->where('status', '!=', 'SYNCING')
            ->update(['status' => 'SYNCING', 'sync_started_at' => now(), 'last_error' => null]);
        if (!$claimed) throw ValidationException::withMessages(['file' => 'Source is disabled or already syncing.']);
        $run = $source->runs()->create(['status' => 'RUNNING', 'started_at' => now(), 'metadata' => ['type' => 'CSV_IMPORT']]);
        try {
            $lines = preg_split('/\R/', $csv);
            $header = str_getcsv(array_shift($lines) ?? '');
            if (!in_array('title', $header, true) || !in_array('body', $header, true)) throw ValidationException::withMessages(['file' => 'CSV needs title and body columns; optional source_url.']);
            $rows = [];
            foreach (array_slice($lines, 0, 200) as $line) {
                if (!trim($line)) continue;
                $columns = str_getcsv($line);
                if (count($columns) !== count($header)) continue;
                $row = array_combine($header, $columns);
                if (!$row || !trim($row['body'] ?? '')) continue;
                $body = mb_substr(trim($row['body']), 0, 20000);
                $rows[] = ['title' => mb_substr($row['title'] ?: $source->name, 0, 255), 'body' => $body, 'source_url' => $row['source_url'] ?? null, 'sha256' => hash('sha256', $body), 'status' => 'PENDING', 'synced_at' => now()];
            }
            if (!$rows) throw ValidationException::withMessages(['file' => 'CSV contains no usable knowledge rows.']);
            DB::transaction(function () use ($source, $run, $rows) {
                foreach ($rows as $row) $source->documents()->create($row);
                $run->update(['status' => 'COMPLETED', 'documents' => count($rows), 'extracted_items' => count($rows), 'content_changed' => true, 'finished_at' => now()]);
                $source->update(['status' => 'SYNCED', 'last_synced_at' => now(), 'last_error' => null, 'sync_started_at' => null, 'next_sync_at' => null]);
            });
            return count($rows);
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 2000);
            $source->update(['status' => 'SYNC_FAILED', 'last_error' => $message, 'last_sync_failed_at' => now(), 'sync_started_at' => null]);
            $run->update(['status' => 'FAILED', 'error' => $message, 'finished_at' => now()]);
            throw $e;
        }
    }
}
