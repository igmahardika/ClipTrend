<?php

namespace App\Http\Controllers;

use App\Models\RenderedVideo;
use App\Models\VideoProject;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaPreviewController extends Controller
{
    public function source(VideoProject $project): BinaryFileResponse|Response
    {
        $this->authorize('view', $project);

        $video = $project->uploadedVideo;
        abort_unless($video && $video->path, 404, 'Source video not found.');

        return $this->privateFileResponse($video->disk, $video->path, $video->mime_type ?: 'video/mp4');
    }

    public function rendered(RenderedVideo $renderedVideo): BinaryFileResponse|Response
    {
        $this->authorize('view', $renderedVideo->project);

        return $this->privateFileResponse($renderedVideo->disk, $renderedVideo->path, 'video/mp4');
    }

    private function privateFileResponse(string $diskName, string $path, string $contentType): BinaryFileResponse|Response
    {
        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($path), 404, 'Media file not found.');

        if (method_exists($disk, 'path')) {
            $absolutePath = $disk->path($path);
            return response()->file($absolutePath, [
                'Content-Type' => $contentType,
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        // S3-compatible fallback: stream content through Laravel when temporary URLs are not configured.
        return response($disk->get($path), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
