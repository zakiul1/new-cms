<?php

namespace App\Cms\Content;

use App\Models\Media;
use App\Models\Post;

class CurrentContentContext
{
    public ?Post $post = null;
    public ?Media $media = null;

    public function setPost(?Post $post): void
    {
        $this->post = $post;
    }

    public function setMedia(?Media $media): void
    {
        $this->media = $media;
    }
}