<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Seo\SitemapStorage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class SitemapController extends Controller
{
    public function __construct(private SitemapStorage $storage)
    {
    }

    /**
     * GET /sitemap.xml
     * Serves sitemap index only if generated.
     */
    public function index(): Response
    {
        $disk = Storage::disk('public');
        $path = $this->storage->indexPath();

        if (!$disk->exists($path)) {
            return response('Sitemap not generated.', 404)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response($disk->get($path) ?: '', 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * GET /{name}.xml (pages.xml, posts.xml, posts-2.xml, media.xml ...)
     */
    public function file(string $name): Response
    {
        $disk = Storage::disk('public');
        $filename = $name . '.xml';
        $path = $this->storage->path($filename);

        if (!$disk->exists($path)) {
            return response('Sitemap not found.', 404)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response($disk->get($path) ?: '', 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}