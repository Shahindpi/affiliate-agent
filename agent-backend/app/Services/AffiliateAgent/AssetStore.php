<?php

namespace App\Services\AffiliateAgent;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetStore
{
    public function put(string $bytes, string $extension, string $mime, bool $mock = false): array
    {
        $path = 'affiliate-agent/assets/'.Str::uuid().'.'.$extension;
        if (!Storage::disk('local')->put($path, $bytes)) throw new \RuntimeException('Asset could not be saved.');
        return $this->manifest($path, $mime, $mock);
    }

    public function upload(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'video/mp4' => 'mp4', 'audio/mpeg' => 'mp3', 'audio/wav', 'audio/x-wav' => 'wav',
            'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp',
            default => throw ValidationException::withMessages(['file' => 'Unsupported asset type.']),
        };
        return $this->put($file->getContent(), $extension, $mime);
    }

    public function copyMedia(int $id): array
    {
        $media = Media::findOrFail($id);
        if (!in_array($media->disk, ['local', 'public'], true) || !Storage::disk($media->disk)->exists($media->path)) throw ValidationException::withMessages(['media' => 'The scene media file is unavailable.']);
        if (!in_array($media->mime_type, ['image/png', 'image/jpeg', 'image/webp', 'video/mp4'], true)) throw ValidationException::withMessages(['media' => 'Scene media must be an image or MP4 video.']);
        return $this->put(Storage::disk($media->disk)->get($media->path), pathinfo($media->path, PATHINFO_EXTENSION), $media->mime_type);
    }

    public function manifest(string $path, string $mime, bool $mock = false): array
    {
        return ['path' => $path, 'mime_type' => $mime, 'sha256' => hash_file('sha256', Storage::disk('local')->path($path)), 'mock' => $mock];
    }

    public function verify(array $asset): bool
    {
        return isset($asset['path'], $asset['sha256']) && str_starts_with($asset['path'], 'affiliate-agent/assets/') && Storage::disk('local')->exists($asset['path']) && hash_equals($asset['sha256'], hash_file('sha256', Storage::disk('local')->path($asset['path'])));
    }

    public function absolute(array $asset): string
    {
        if (!$this->verify($asset)) throw new \RuntimeException('Asset checksum failed or asset is missing.');
        return Storage::disk('local')->path($asset['path']);
    }
}
