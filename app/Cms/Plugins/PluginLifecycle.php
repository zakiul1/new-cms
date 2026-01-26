<?php

namespace App\Cms\Plugins;

use App\Cms\Core\Settings;
use Illuminate\Support\Facades\Log;
use Throwable;

class PluginLifecycle
{
    public function __construct(
        private readonly Settings $settings,
        private readonly PluginManager $plugins,
    ) {}

    public function activate(string $slug): void
    {
        $m = $this->plugins->manifest($slug);
        if (!$m) return;

        $this->runFile($slug, $m->lifecycle['activate'] ?? 'activate.php');

        // store installed info
        $this->settings->set("plugins.{$slug}.installed_version", $m->version ?? '0.0.0', 'core');
        $this->settings->set("plugins.{$slug}.activated_at", now()->toISOString(), 'core');
        $this->settings->forget("plugins.{$slug}.last_error", 'core');
    }

    public function deactivate(string $slug): void
    {
        $m = $this->plugins->manifest($slug);
        if (!$m) return;

        $this->runFile($slug, $m->lifecycle['deactivate'] ?? 'deactivate.php');

        $this->settings->set("plugins.{$slug}.deactivated_at", now()->toISOString(), 'core');
    }

    public function uninstall(string $slug): void
    {
        $m = $this->plugins->manifest($slug);
        if ($m) {
            $this->runFile($slug, $m->lifecycle['uninstall'] ?? 'uninstall.php');
        }

        // cleanup stored meta
        $this->settings->forget("plugins.{$slug}.installed_version", 'core');
        $this->settings->forget("plugins.{$slug}.activated_at", 'core');
        $this->settings->forget("plugins.{$slug}.deactivated_at", 'core');
        $this->settings->forget("plugins.{$slug}.last_error", 'core');
    }

    private function runFile(string $slug, string $relative): void
    {
        $relative = trim($relative);
        if ($relative === '') return;

        $file = base_path('plugins/' . $slug . '/' . ltrim($relative, '/'));
        if (!is_file($file)) {
            return; // lifecycle file optional
        }

        try {
            require $file;
        } catch (Throwable $e) {
            Log::error('Plugin lifecycle failed', [
                'plugin' => $slug,
                'file' => $file,
                'error' => $e->getMessage(),
            ]);

            // store last error so UI can show it
            $this->settings->set("plugins.{$slug}.last_error", $e->getMessage(), 'core');

            throw $e;
        }
    }
}
