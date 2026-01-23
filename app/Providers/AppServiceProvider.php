<?php

namespace App\Providers;
use App\Models\Post;
use App\Observers\PostObserver;
use Livewire\Livewire;
use App\Livewire\MediaBrowser;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Post::observe(PostObserver::class);
         Livewire::component('media-browser', MediaBrowser::class);
    }
}