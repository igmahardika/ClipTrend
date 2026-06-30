<?php

namespace App\Services\Storage;

use App\Models\Clip;
use App\Models\UploadedVideo;
use App\Models\VideoProject;
use Illuminate\Support\Str;

class MediaPathService
{
    public function projectBase(VideoProject $project): string
    {
        return 'users/'.$project->user_id.'/projects/'.$project->id.'-'.$project->slug;
    }

    public function uploads(VideoProject $project, string $extension): string
    {
        return $this->projectBase($project).'/uploads/source-'.now()->format('YmdHis').'-'.Str::random(8).'.'.$extension;
    }

    public function audio(UploadedVideo $video): string
    {
        return $this->projectBase($video->project).'/analysis/audio-'.$video->id.'.wav';
    }

    public function rendered(Clip $clip, string $platform): string
    {
        return $this->projectBase($clip->project).'/renders/clip-'.$clip->id.'-'.$platform.'-'.Str::random(8).'.mp4';
    }
}
