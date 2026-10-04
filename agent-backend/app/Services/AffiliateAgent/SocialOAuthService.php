<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\SocialAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialOAuthService
{
    public const SCOPES = [
        'pinterest' => ['boards:read','boards:write','pins:read','pins:write','user_accounts:read'],
        'youtube' => ['https://www.googleapis.com/auth/youtube.upload','https://www.googleapis.com/auth/youtube.readonly'],
        'tiktok' => ['user.info.basic','video.upload','video.publish'],
        'meta' => ['pages_show_list','pages_read_engagement','pages_manage_posts','instagram_basic','instagram_content_publish'],
    ];

    private function config(string $platform): array
    {
        abort_unless(isset(self::SCOPES[$platform]), 404);
        $config = config('affiliate_agent.oauth.'.$platform);
        abort_unless($config['id'] && $config['secret'], 409, strtoupper($platform).' developer app credentials are missing.');
        return $config;
    }

    public function callbackUrl(string $platform): string { return route('agent.oauth.callback', ['platform' => $platform]); }

    public function begin(string $platform, int $userId): string
    {
        $c = $this->config($platform);
        $state = Str::random(48);
        Cache::put('agent-oauth:'.$state, ['platform' => $platform, 'user_id' => $userId], now()->addMinutes(10));
        $redirect = $this->callbackUrl($platform);
        $scope = implode($platform === 'youtube' ? ' ' : ',', self::SCOPES[$platform]);
        $params = ['response_type' => 'code', 'redirect_uri' => $redirect, 'state' => $state, 'scope' => $scope];
        if ($platform === 'tiktok') $params['client_key'] = $c['id']; else $params['client_id'] = $c['id'];
        if ($platform === 'youtube') $params += ['access_type' => 'offline', 'prompt' => 'consent'];
        $base = match ($platform) {
            'pinterest' => 'https://www.pinterest.com/oauth/',
            'youtube' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'tiktok' => 'https://www.tiktok.com/v2/auth/authorize/',
            'meta' => 'https://www.facebook.com/'.config('affiliate_agent.meta_graph_version').'/dialog/oauth',
        };
        return $base.'?'.http_build_query($params);
    }

    public function complete(string $platform, string $state, string $code): void
    {
        $session = Cache::pull('agent-oauth:'.$state);
        abort_unless($session && $session['platform'] === $platform && \App\Models\User::whereKey($session['user_id'])->whereHas('role', fn ($q) => $q->where('slug', 'admin'))->exists(), 403, 'OAuth state expired or invalid.');
        $c = $this->config($platform);
        $redirect = $this->callbackUrl($platform);
        $token = match ($platform) {
            'pinterest' => Http::withBasicAuth($c['id'], $c['secret'])->asForm()->post('https://api.pinterest.com/v5/oauth/token', ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirect])->throw()->json(),
            'youtube' => Http::asForm()->post('https://oauth2.googleapis.com/token', ['client_id' => $c['id'], 'client_secret' => $c['secret'], 'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => $redirect])->throw()->json(),
            'tiktok' => Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', ['client_key' => $c['id'], 'client_secret' => $c['secret'], 'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => $redirect])->throw()->json(),
            'meta' => Http::get('https://graph.facebook.com/'.config('affiliate_agent.meta_graph_version').'/oauth/access_token', ['client_id' => $c['id'], 'client_secret' => $c['secret'], 'code' => $code, 'redirect_uri' => $redirect])->throw()->json(),
        };
        $access = $token['access_token'] ?? throw new \RuntimeException('Provider did not return an access token.');
        $expiry = isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null;
        $scopes = isset($token['scope']) ? preg_split('/[ ,]+/', $token['scope']) : self::SCOPES[$platform];
        if ($platform === 'youtube') {
            $channels = Http::withToken($access)->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true'])->throw()->json('items');
            if (!$channels) throw new \RuntimeException('No YouTube channel found for this Google account.');
            foreach ($channels as $channel) $this->save('youtube', $channel['id'], $channel['snippet']['title'], $access, $token['refresh_token'] ?? null, $expiry, $scopes, ['channel' => $channel['snippet']], 'UNKNOWN');
        } elseif ($platform === 'pinterest') {
            $profile = Http::withToken($access)->get('https://api.pinterest.com/v5/user_account')->throw()->json();
            $this->save('pinterest', $profile['username'] ?? $profile['id'], $profile['username'] ?? 'Pinterest', $access, $token['refresh_token'] ?? null, $expiry, $scopes, [], 'UNKNOWN');
        } elseif ($platform === 'tiktok') {
            $profile = Http::withToken($access)->get('https://open.tiktokapis.com/v2/user/info/', ['fields' => 'open_id,display_name'])->throw()->json('data.user');
            $this->save('tiktok', $profile['open_id'] ?? $token['open_id'], $profile['display_name'] ?? 'TikTok', $access, $token['refresh_token'] ?? null, $expiry, $scopes, [], 'API_REVIEW_REQUIRED');
        } else {
            $pages = Http::withToken($access)->get('https://graph.facebook.com/'.config('affiliate_agent.meta_graph_version').'/me/accounts', ['fields' => 'id,name,access_token,instagram_business_account{id,username}'])->throw()->json('data');
            if (!$pages) throw new \RuntimeException('No authorized Facebook Page found.');
            foreach ($pages as $page) {
                $pageToken = $page['access_token'] ?? $access;
                $this->save('facebook', $page['id'], $page['name'], $pageToken, null, null, $scopes, [], 'UNKNOWN');
                if (isset($page['instagram_business_account']['id'])) {
                    $ig = $page['instagram_business_account'];
                    $this->save('instagram', $ig['id'], $ig['username'] ?? 'Instagram', $pageToken, null, null, $scopes, ['page_id' => $page['id']], 'UNKNOWN');
                }
            }
        }
    }

    private function save(string $platform, string $id, string $name, string $token, ?string $refresh, ?\DateTimeInterface $expiry, array $scopes, array $metadata, string $review): void
    {
        $account = SocialAccount::firstOrNew(['platform' => $platform, 'external_id' => $id]);
        $account->fill(['name' => $name, 'access_token' => $token, 'refresh_token' => $refresh ?: $account->refresh_token, 'expires_at' => $expiry, 'scopes' => $scopes, 'metadata' => $metadata, 'status' => 'CONNECTED', 'api_review_status' => $review, 'last_verified_at' => now(), 'last_error' => null]);
        $account->save();
    }

    public function token(SocialAccount $account): string
    {
        if ($account->expires_at && $account->expires_at->lte(now()->addMinutes(5))) {
            if (!$account->refresh_token || !in_array($account->platform, ['youtube','pinterest','tiktok'])) { $account->update(['status' => 'TOKEN_EXPIRED']); throw new \RuntimeException('Token expired; reconnect this account.'); }
            $c = $this->config($account->platform);
            $response = match ($account->platform) {
                'youtube' => Http::asForm()->post('https://oauth2.googleapis.com/token', ['client_id' => $c['id'], 'client_secret' => $c['secret'], 'refresh_token' => $account->refresh_token, 'grant_type' => 'refresh_token'])->throw()->json(),
                'pinterest' => Http::withBasicAuth($c['id'], $c['secret'])->asForm()->post('https://api.pinterest.com/v5/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token])->throw()->json(),
                'tiktok' => Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', ['client_key' => $c['id'], 'client_secret' => $c['secret'], 'grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token])->throw()->json(),
            };
            $account->update(['access_token' => $response['access_token'], 'refresh_token' => $response['refresh_token'] ?? $account->refresh_token, 'expires_at' => now()->addSeconds($response['expires_in'] ?? 3600), 'status' => 'CONNECTED']);
        }
        return $account->access_token;
    }
}
