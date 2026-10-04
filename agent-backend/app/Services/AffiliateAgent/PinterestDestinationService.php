<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\{Content,PinterestDestination,SocialAccount};
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PinterestDestinationService
{
    private function request(SocialAccount $account)
    {
        abort_unless($account->platform === 'pinterest' && $account->status === 'CONNECTED', 409, 'Connect Pinterest first.');
        return Http::withToken(app(SocialOAuthService::class)->token($account))->timeout(20);
    }

    public function boards(SocialAccount $account): array
    {
        $response = $this->request($account)->get('https://api.pinterest.com/v5/boards', ['page_size' => 100])->throw()->json();
        return ['items' => collect($response['items'] ?? [])->map(fn ($b) => ['id' => $b['id'], 'name' => $b['name']])->all(), 'bookmark' => $response['bookmark'] ?? null];
    }

    public function sections(SocialAccount $account, string $boardId): array
    {
        $response = $this->request($account)->get('https://api.pinterest.com/v5/boards/'.rawurlencode($boardId).'/sections', ['page_size' => 100])->throw()->json();
        return ['items' => collect($response['items'] ?? [])->map(fn ($s) => ['id' => $s['id'], 'name' => $s['name']])->all(), 'bookmark' => $response['bookmark'] ?? null];
    }

    public function createBoard(SocialAccount $account, string $name): array
    {
        return $this->request($account)->post('https://api.pinterest.com/v5/boards', ['name' => $name])->throw()->json();
    }

    public function createSection(SocialAccount $account, string $boardId, string $name): array
    {
        return $this->request($account)->post('https://api.pinterest.com/v5/boards/'.rawurlencode($boardId).'/sections', ['name' => $name])->throw()->json();
    }

    public function save(SocialAccount $account, array $data): PinterestDestination
    {
        $board = $this->request($account)->get('https://api.pinterest.com/v5/boards/'.rawurlencode($data['external_board_id']))->throw()->json();
        if (($board['id'] ?? null) !== $data['external_board_id']) throw ValidationException::withMessages(['external_board_id' => 'Board ID was not verified by Pinterest.']);
        $sectionName = null;
        if (!empty($data['external_section_id'])) {
            $sections = $this->sections($account, $data['external_board_id'])['items'];
            $sectionName = collect($sections)->firstWhere('id', $data['external_section_id'])['name'] ?? null;
            if (!$sectionName) throw ValidationException::withMessages(['external_section_id' => 'Section must belong to the selected board.']);
        }
        $scopeId = $data['scope_type'] === 'account' ? 0 : (int) $data['scope_id'];
        $model = PinterestDestination::firstOrNew(['social_account_id' => $account->id, 'scope_type' => $data['scope_type'], 'scope_id' => $scopeId]);
        $model->fill(['external_board_id' => $board['id'], 'external_board_name' => $board['name'], 'external_section_id' => $data['external_section_id'] ?? null, 'external_section_name' => $sectionName]);
        $model->save();
        return $model;
    }

    public function forContent(SocialAccount $account, Content $content): ?PinterestDestination
    {
        foreach (['content' => $content->id, 'campaign' => $content->campaign_id, 'product' => $content->affiliate_product_id, 'brand' => $content->brand_id, 'account' => 0] as $scope => $id) {
            if ($scope !== 'account' && !$id) continue;
            $destination = PinterestDestination::where('social_account_id', $account->id)->where('scope_type', $scope)->where('scope_id', $id)->latest()->first();
            if ($destination) return $destination;
        }
        return null;
    }
}
