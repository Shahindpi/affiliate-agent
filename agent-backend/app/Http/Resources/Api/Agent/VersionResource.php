<?php

namespace App\Http\Resources\Api\Agent;

use App\Services\AffiliateAgent\AssetStore;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class VersionResource extends JsonResource
{
    public function toArray($request): array
    {
        $assets = [];
        foreach (['video', 'voice'] as $key) {
            $assets[$key] = ['mock' => $this->artifacts[$key]['mock'] ?? false, 'url' => URL::temporarySignedRoute('agent.asset', now()->addMinutes(30), ['version' => $this->id, 'kind' => $key])];
        }
        return [
            'id' => $this->id, 'number' => $this->number, 'parent_version_id' => $this->parent_version_id,
            'restored_from_id' => $this->restored_from_id, 'snapshot' => $this->snapshot,
            'assets' => $assets, 'snapshot_hash' => $this->snapshot_hash, 'changes' => $this->changes,
            'steps' => $this->steps, 'qa' => $this->qa, 'references' => $this->references ?? [], 'mock' => $this->mock,
            'created_by' => $this->created_by, 'created_at' => $this->created_at,
        ];
    }
}
