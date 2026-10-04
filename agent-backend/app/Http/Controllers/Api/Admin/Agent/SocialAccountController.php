<?php

namespace App\Http\Controllers\Api\Admin\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent\SocialAccount;
use App\Models\Agent\PinterestDestination;
use App\Services\AffiliateAgent\PinterestDestinationService;
use App\Services\AffiliateAgent\SocialOAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SocialAccountController extends Controller
{
    public function index() { return ApiResponse::success(['accounts' => SocialAccount::all(), 'configured' => collect(config('affiliate_agent.oauth'))->map(fn ($c) => (bool) ($c['id'] && $c['secret']))]); }

    public function connect(string $platform, Request $r, SocialOAuthService $oauth)
    {
        return ApiResponse::success(['authorization_url' => $oauth->begin($platform, $r->user()->id)]);
    }

    public function callback(string $platform, Request $r, SocialOAuthService $oauth)
    {
        $r->validate(['state' => 'required|string', 'code' => 'required|string']);
        $oauth->complete($platform, $r->input('state'), $r->input('code'));
        return redirect()->away(config('affiliate_agent.admin_url').'?connected='.rawurlencode($platform));
    }

    public function update(SocialAccount $account, Request $r)
    {
        $data = $r->validate(['publishing_enabled' => 'sometimes|boolean', 'api_review_status' => 'sometimes|in:UNKNOWN,API_REVIEW_REQUIRED,APPROVED', 'metadata' => 'sometimes|array', 'metadata.default_board_id' => 'sometimes|string|max:255', 'metadata.cover_image_url' => 'sometimes|url:https|max:2000']);
        if (isset($data['metadata'])) $data['metadata'] = array_merge($account->metadata ?? [], $data['metadata']);
        $account->update($data);
        return ApiResponse::success($account);
    }

    public function disconnect(SocialAccount $account)
    {
        // Do not delete historical publication references.
        $account->update(['access_token' => null, 'refresh_token' => null, 'publishing_enabled' => false, 'status' => 'AUTHORIZATION_REQUIRED']);
        return ApiResponse::success($account);
    }

    public function boards(SocialAccount $account, PinterestDestinationService $service)
    {
        return ApiResponse::success($service->boards($account));
    }

    public function sections(SocialAccount $account, string $boardId, PinterestDestinationService $service)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $boardId), 422);
        return ApiResponse::success($service->sections($account, $boardId));
    }

    public function createBoard(SocialAccount $account, Request $r, PinterestDestinationService $service)
    {
        $data = $r->validate(['name' => 'required|string|max:100']);
        return ApiResponse::success($service->createBoard($account, $data['name']), 'Board created.', 201);
    }

    public function createSection(SocialAccount $account, string $boardId, Request $r, PinterestDestinationService $service)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $boardId), 422);
        $data = $r->validate(['name' => 'required|string|max:100']);
        return ApiResponse::success($service->createSection($account, $boardId, $data['name']), 'Section created.', 201);
    }

    public function destinations(SocialAccount $account)
    {
        abort_unless($account->platform === 'pinterest', 404);
        return ApiResponse::success(PinterestDestination::where('social_account_id', $account->id)->get());
    }

    public function saveDestination(SocialAccount $account, Request $r, PinterestDestinationService $service)
    {
        $data = $r->validate(['scope_type' => 'required|in:account,brand,product,campaign,content', 'scope_id' => 'nullable|integer|min:1', 'external_board_id' => 'required|string|max:100', 'external_section_id' => 'nullable|string|max:100']);
        if ($data['scope_type'] !== 'account') {
            $table = ['brand' => 'brands', 'product' => 'affiliate_products', 'campaign' => 'agent_campaigns', 'content' => 'agent_contents'][$data['scope_type']];
            if (empty($data['scope_id']) || !\Illuminate\Support\Facades\DB::table($table)->where('id', $data['scope_id'])->exists()) return response()->json(['message' => 'Select an existing scope item.'], 422);
        }
        return ApiResponse::success($service->save($account, $data));
    }

    public function test(SocialAccount $account, SocialOAuthService $oauth)
    {
        if (!$account->access_token) return ApiResponse::success(['ok' => false, 'message' => 'Reconnect this account.']);
        try {
            $token = $oauth->token($account);
            $url = match ($account->platform) {
                'pinterest' => 'https://api.pinterest.com/v5/user_account',
                'youtube' => 'https://www.googleapis.com/youtube/v3/channels?part=id&mine=true',
                'tiktok' => 'https://open.tiktokapis.com/v2/user/info/?fields=open_id',
                'facebook','instagram' => 'https://graph.facebook.com/'.config('affiliate_agent.meta_graph_version').'/'.$account->external_id.'?fields=id',
            };
            $response = Http::withToken($token)->timeout(15)->get($url);
            $ok = $response->successful();
            $account->update(['status' => $ok ? 'CONNECTED' : 'ERROR', 'last_verified_at' => now(), 'last_error' => $ok ? null : 'Platform verification returned HTTP '.$response->status()]);
            return ApiResponse::success(['ok' => $ok, 'message' => $ok ? 'Account verified with platform API.' : $account->last_error]);
        } catch (\Throwable $e) {
            $account->update(['status' => 'ERROR', 'last_error' => mb_substr($e->getMessage(), 0, 500)]);
            return ApiResponse::success(['ok' => false, 'message' => $account->last_error]);
        }
    }
}
