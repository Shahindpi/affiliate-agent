<?php

namespace App\Http\Controllers\Api\Admin\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ContentRequest;
use App\Http\Requests\Agent\RevisionRequest;
use App\Http\Resources\Api\Agent\VersionResource;
use App\Models\Agent\Content;
use App\Models\Agent\ContentVersion;
use App\Models\Agent\Publication;
use App\Models\Agent\Usage;
use App\Models\Media;
use App\Services\AffiliateAgent\AssetStore;
use App\Services\AffiliateAgent\ContentSchema;
use App\Services\AffiliateAgent\PublishingAgent;
use App\Services\AffiliateAgent\ReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    public function index(Request $r)
    {
        $data = $r->validate(['status' => 'sometimes|nullable|string|max:50', 'search' => 'sometimes|nullable|string|max:255', 'page' => 'sometimes|integer|min:1']);
        $query = Content::with(['brand:id,name', 'product:id,name'])->latest();
        if ($r->filled('status')) $query->where('status', $data['status']);
        if ($r->filled('search')) $query->where('title', 'like', '%'.$data['search'].'%');
        return ApiResponse::success($query->paginate(20));
    }

    public function overview()
    {
        return ApiResponse::success(['counts' => Content::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'), 'ai_provider' => config('affiliate_agent.provider'), 'voice_provider' => config('affiliate_agent.voice_provider'), 'publishing' => 'Connect social accounts and confirm platform app permissions in Setup. Manual export remains available for every approved version.', 'usage' => Usage::where('created_at', '>=', now()->startOfMonth())->selectRaw('provider, operation, count(*) as jobs, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, sum(characters) as characters, sum(estimated_cost) as estimated_cost, sum(duration_seconds) as duration_seconds')->groupBy('provider', 'operation')->get()]);
    }

    public function store(ContentRequest $r, ReviewService $review)
    {
        return ApiResponse::success($review->create($r->validated(), $r->user()->id), 'Content queued for rendering and review.', 201);
    }

    public function show(Content $content)
    {
        $content->load(['brand:id,name', 'product:id,name', 'feedback', 'approvals', 'publications', 'versions']);
        return ApiResponse::success([...$content->toArray(), 'versions' => VersionResource::collection($content->versions)]);
    }

    public function revision(RevisionRequest $r, Content $content, ReviewService $review)
    {
        return ApiResponse::success($review->revision($content->id, $r->validated(), $r->user()->id), 'Revision queued. Approval has been invalidated.', 202);
    }

    public function locks(Request $r, Content $content, ReviewService $review)
    {
        $d = $r->validate(['expected_version_id' => 'required|integer', 'locks' => 'present|array|max:30', 'locks.*' => 'string|distinct|max:100']);
        return ApiResponse::success($review->locks($content->id, $d['expected_version_id'], $d['locks']));
    }

    public function approve(Request $r, Content $content, ReviewService $review)
    {
        $d = $r->validate(['expected_version_id' => 'required|integer']);
        return ApiResponse::success($review->approve($content->id, $d['expected_version_id'], $r->user()->id), 'Exact version FINAL_APPROVED.');
    }

    public function reject(Request $r, Content $content, ReviewService $review)
    {
        $d = $r->validate(['expected_version_id' => 'required|integer', 'reason' => 'required|string|max:6000']);
        return ApiResponse::success($review->reject($content->id, $d['expected_version_id'], $d['reason'], $r->user()->id));
    }

    public function restore(Request $r, Content $content, ReviewService $review)
    {
        $d = $r->validate(['expected_version_id' => 'required|integer', 'source_version_id' => 'required|integer']);
        return ApiResponse::success(new VersionResource($review->restore($content->id, $d['expected_version_id'], $d['source_version_id'], $r->user()->id)), 'Restored as a new version requiring approval.');
    }

    public function schedule(Request $r, Content $content, PublishingAgent $agent)
    {
        $d = $r->validate(['expected_version_id' => 'required|integer', 'platform' => ['required', Rule::in(ContentSchema::PLATFORMS)], 'mode' => ['required', Rule::in(['manual', 'mock', 'api'])], 'social_account_id' => 'nullable|integer|exists:agent_social_accounts,id', 'scheduled_at' => 'nullable|date|after_or_equal:now']);
        return ApiResponse::success($agent->schedule($content->id, $d, $r->user()->id), 'Publication scheduled.', 201);
    }

    public function export(Request $r, Content $content, PublishingAgent $agent)
    {
        $d = $r->validate(['version_id' => 'required|integer']);
        $v = $agent->approved($content, $d['version_id']);
        return response()->streamDownload(fn () => print(json_encode(['version_id' => $v->id, 'snapshot_hash' => $v->snapshot_hash, 'mock' => $v->mock, 'metadata' => $v->snapshot['metadata']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'content-'.$content->id.'-v'.$v->number.'-metadata.json');
    }

    public function confirm(Request $r, Publication $publication, PublishingAgent $agent)
    {
        $d = $r->validate(['external_id' => 'required|url:http,https|max:2000']);
        return ApiResponse::success($agent->confirmManual($publication->id, $d['external_id']));
    }

    public function upload(Request $r, AssetStore $store)
    {
        $r->validate(['file' => 'required|file|max:102400|mimetypes:video/mp4,audio/mpeg,audio/wav,audio/x-wav,image/png,image/jpeg,image/webp', 'name' => 'required|string|max:255']);
        $a = $store->upload($r->file('file'));
        $media = Media::create(['user_id' => $r->user()->id, 'name' => $r->input('name'), 'file_name' => basename($a['path']), 'disk' => 'local', 'path' => $a['path'], 'mime_type' => $a['mime_type'], 'size' => Storage::disk('local')->size($a['path']), 'folder' => 'affiliate-agent']);
        return ApiResponse::success($media, 'Immutable agent asset uploaded.', 201);
    }

    public function media()
    {
        return ApiResponse::success(Media::where('folder', 'affiliate-agent')->latest()->limit(100)->get(['id', 'name', 'mime_type']));
    }

    public function publications()
    {
        return ApiResponse::success(Publication::with(['account:id,platform,name,status', 'content:id,title,status'])->latest()->paginate(30));
    }

    // Signed URLs are capability links, issued only to authenticated admins.
    // Native <video>/<audio> cannot set Sanctum Authorization headers.
    public function asset(ContentVersion $version, string $kind, AssetStore $store)
    {
        abort_unless(in_array($kind, ['video', 'voice'], true), 404);
        return response()->file($store->absolute($version->artifacts[$kind]), ['Content-Type' => $version->artifacts[$kind]['mime_type'], 'Cache-Control' => 'private, no-store']);
    }
}
