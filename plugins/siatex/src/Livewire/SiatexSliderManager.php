<?php

namespace Plugins\siatex\Livewire;

use Livewire\Component;
use App\Models\Media;
use App\Models\Slider;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Notifications\Notification;

class SiatexSliderManager extends Component
{
    public ?int $editingId = null;

    /**
     * ✅ NEW: Show the right-side form only when:
     * - user clicks "New", or
     * - user clicks a slider to edit
     */
    public bool $showForm = false;

    public string $name = '';
    public string $key = '';
    public bool $is_active = true;

    // Siatex meta (stored inside settings_json)
    public string $siatex_variant = 'default';
    public ?int $category_id = null;

    // Global content
    public string $title = '';
    public string $subtitle = '';

    // Options (shared)
    public bool $show_navigation = true;
    public bool $show_indicators = true;
    public string $indicator_style = 'dots'; // dots|flat (used by default slider)

    // ✅ CHANGE: default speed 3000ms
    public int $delay = 3000;

    public string $layout = 'image-left'; // image-left|image-right (default slider)
    public string $height = '560px';
    public string $bg_color = '#f5f5f5';

    // ✅ Cinematic Pro motion controls (GSAP + Swiper)
    public float $motion_duration = 6.5; // seconds
    public float $zoom_min = 1.06;
    public float $zoom_max = 1.18;
    public float $pan_strength = 2.0; // percent
    public float $rotate = 0.6; // degrees
    public int $fade_ms = 900; // swiper fade speed in ms

    // ✅ CHANGE: default overlay 0.2
    public float $overlay_opacity = 0.2; // 0..0.75

    public bool $preview = true;

    /**
     * ✅ Central place to define available variants shown in admin,
     * and used to compute the renderer "variant" key.
     */
    public function siatexVariantOptions(): array
    {
        return [
            'default' => 'Siatex: Default',
            'cinematic-pro' => 'Siatex: Cinematic Pro (GSAP + Swiper)',
        ];
    }

    /**
     * ✅ Maps siatex_variant -> renderer variant stored in settings_json['variant']
     * This is what siatex_slider_render() uses.
     */
    private function rendererVariantFromSiatexVariant(string $siatexVariant): string
    {
        return match ($siatexVariant) {
            'cinematic-pro' => 'siatex-cinematic-pro',
            default => 'siatex-default',
        };
    }

    private function mediaCategoryTaxonomyId(): int
    {
        return (int) Taxonomy::firstOrCreate(
            ['key' => 'media_category'],
            ['label' => 'Media Categories', 'hierarchical' => true],
        )->id;
    }

    /**
     * ✅ NEW: Runtime change defaults when variant changes in the UI
     * (fields show/hide will be handled in Blade, but this keeps sensible defaults).
     */
    public function updatedSiatexVariant(string $value): void
    {
        // Guard: only allow known variants
        if (!array_key_exists($value, $this->siatexVariantOptions())) {
            $this->siatex_variant = 'default';
            return;
        }

        // Only cinematic-pro defaults nav/indicators OFF
        if ($value === 'cinematic-pro') {
            $this->show_navigation = false;
            $this->show_indicators = false;
        } else {
            $this->show_navigation = true;
            $this->show_indicators = true;
        }
    }

    /**
     * ✅ NEW: When Media Category changes, auto sync slides (no sync button needed).
     */
    public function updatedCategoryId($value): void
    {
        // If no slider saved yet, do nothing (user must click Save first)
        if (!$this->editingId) {
            return;
        }

        // Auto-sync silently (no "Select category first" toast spam)
        $this->syncFromCategory(false);
    }

    public function createNew(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $slider = Slider::findOrFail($id);

        $this->showForm = true;
        $this->editingId = $slider->id;

        $this->name = (string) $slider->name;
        $this->key = (string) $slider->key;
        $this->is_active = (bool) $slider->is_active;

        $settings = (array) ($slider->settings_json ?? []);

        // normalize old data: if someone had 'cinematic', force to default
        $variant = (string) ($settings['siatex_variant'] ?? 'default');
        if ($variant === 'cinematic') {
            $variant = 'default';
        }

        $this->siatex_variant = $variant;
        $this->category_id = $settings['category_id'] ?? null;

        $this->title = (string) ($settings['title'] ?? '');
        $this->subtitle = (string) ($settings['subtitle'] ?? '');

        // ✅ Only cinematic-pro should default nav/indicators OFF
        $isCinePro = ($this->siatex_variant === 'cinematic-pro');
        $defaultNav = $isCinePro ? false : true;
        $defaultDots = $isCinePro ? false : true;

        $this->show_navigation = (bool) ($settings['show_navigation'] ?? $defaultNav);
        $this->show_indicators = (bool) ($settings['show_indicators'] ?? $defaultDots);
        $this->indicator_style = (string) ($settings['indicator_style'] ?? 'dots');

        // ✅ CHANGE: default delay 3000
        $this->delay = (int) ($settings['delay'] ?? 3000);

        $this->layout = (string) ($settings['layout'] ?? 'image-left');
        $this->height = (string) ($settings['height'] ?? '560px');
        $this->bg_color = (string) ($settings['bg_color'] ?? '#f5f5f5');

        // ✅ Cinematic Pro motion settings
        $this->motion_duration = (float) ($settings['motion_duration'] ?? 6.5);
        $this->zoom_min = (float) ($settings['zoom_min'] ?? 1.06);
        $this->zoom_max = (float) ($settings['zoom_max'] ?? 1.18);
        $this->pan_strength = (float) ($settings['pan_strength'] ?? 2.0);
        $this->rotate = (float) ($settings['rotate'] ?? 0.6);
        $this->fade_ms = (int) ($settings['fade_ms'] ?? 900);

        // ✅ CHANGE: overlay default 0.2
        $this->overlay_opacity = (float) ($settings['overlay_opacity'] ?? 0.2);

        $this->preview = (bool) ($settings['preview'] ?? true);

        // ✅ Normalize invalid variants (if old data)
        if (!array_key_exists($this->siatex_variant, $this->siatexVariantOptions())) {
            $this->siatex_variant = 'default';
        }

        // ✅ Guard values
        $this->motion_duration = max(2.0, (float) $this->motion_duration);
        $this->zoom_min = max(1.0, (float) $this->zoom_min);
        $this->zoom_max = max($this->zoom_min, (float) $this->zoom_max);
        $this->pan_strength = max(0.0, (float) $this->pan_strength);
        $this->rotate = max(0.0, (float) $this->rotate);
        $this->fade_ms = max(100, (int) $this->fade_ms);

        // overlay range 0..0.75
        $this->overlay_opacity = min(0.75, max(0.0, (float) $this->overlay_opacity));
    }

    public function save(): void
    {
        $slider = $this->editingId
            ? Slider::findOrFail($this->editingId)
            : new Slider();

        $slider->name = $this->name;
        $slider->key = $this->key;
        $slider->is_active = $this->is_active;

        $existing = (array) ($slider->settings_json ?? []);

        // ✅ Guard: only allow known variants
        if (!array_key_exists($this->siatex_variant, $this->siatexVariantOptions())) {
            $this->siatex_variant = 'default';
        }

        // ✅ Guard values
        $this->motion_duration = max(2.0, (float) $this->motion_duration);
        $this->zoom_min = max(1.0, (float) $this->zoom_min);
        $this->zoom_max = max($this->zoom_min, (float) $this->zoom_max);
        $this->pan_strength = max(0.0, (float) $this->pan_strength);
        $this->rotate = max(0.0, (float) $this->rotate);
        $this->fade_ms = max(100, (int) $this->fade_ms);

        // overlay range 0..0.75
        $this->overlay_opacity = min(0.75, max(0.0, (float) $this->overlay_opacity));

        $slider->settings_json = array_merge($existing, [
            // ✅ IMPORTANT for renderer
            'variant' => $this->rendererVariantFromSiatexVariant($this->siatex_variant),

            // plugin flags
            'plugin' => 'siatex',
            'siatex_variant' => $this->siatex_variant,

            // category
            'category_id' => $this->category_id,

            // global text
            'title' => $this->title,
            'subtitle' => $this->subtitle,

            // options
            'show_navigation' => $this->show_navigation,
            'show_indicators' => $this->show_indicators,
            'indicator_style' => $this->indicator_style,
            'delay' => $this->delay,
            'layout' => $this->layout,
            'height' => $this->height,
            'bg_color' => $this->bg_color,

            // cinematic-pro motion
            'motion_duration' => $this->motion_duration,
            'zoom_min' => $this->zoom_min,
            'zoom_max' => $this->zoom_max,
            'pan_strength' => $this->pan_strength,
            'rotate' => $this->rotate,
            'fade_ms' => $this->fade_ms,

            // overlay
            'overlay_opacity' => $this->overlay_opacity,

            'preview' => $this->preview,
        ]);

        $slider->save();

        $this->editingId = $slider->id;
        $this->key = (string) $slider->key;
        $this->showForm = true;

        Notification::make()
            ->title('Saved')
            ->body('Slider settings updated successfully.')
            ->success()
            ->send();

        // ✅ Optional UX: if category already selected, auto-sync after save
        if ($this->category_id) {
            $this->syncFromCategory(false);
        }
    }

    public function deleteSlider(int $id): void
    {
        $slider = Slider::findOrFail($id);

        $slider->slides()->delete();
        $slider->delete();

        if ($this->editingId === $id) {
            $this->editingId = null;
            $this->showForm = false;
            $this->resetForm();
        }

        Notification::make()
            ->title('Deleted')
            ->body('Slider deleted successfully.')
            ->success()
            ->send();
    }

    /**
     * Pull slides from selected Media Category (Term).
     * ✅ Now supports silent mode to avoid spam when auto-syncing.
     */
    public function syncFromCategory(bool $notify = true): void
    {
        if (!$this->editingId || !$this->category_id) {
            if ($notify) {
                Notification::make()
                    ->title('Select a category first')
                    ->body('Please choose a Media Category before syncing slides.')
                    ->warning()
                    ->send();
            }
            return;
        }

        $slider = Slider::findOrFail($this->editingId);

        $mediaIds = Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->whereHas('categories', function ($q) {
                $q->where('terms.id', $this->category_id);
            })
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        if (empty($mediaIds)) {
            if ($notify) {
                Notification::make()
                    ->title('No images found')
                    ->body('No images found in this category. Please assign images to this category first.')
                    ->warning()
                    ->send();
            }
            return;
        }

        foreach ($mediaIds as $mediaId) {
            $slider->slides()->firstOrCreate(
                ['media_id' => $mediaId],
                ['sort_order' => 999, 'is_active' => true]
            );
        }

        // normalize sort order
        $slides = $slider->slides()->orderBy('sort_order')->get();
        foreach ($slides as $i => $slide) {
            $slide->sort_order = $i + 1;
            $slide->save();
        }

        if ($notify) {
            Notification::make()
                ->title('Slides updated')
                ->body('Slides auto-added from category (' . count($mediaIds) . ' images).')
                ->success()
                ->send();
        }
    }

    public function removeSlide(int $slideId): void
    {
        if (!$this->editingId) {
            return;
        }

        $slider = Slider::findOrFail($this->editingId);
        $slider->slides()->where('id', $slideId)->delete();

        // ✅ CHANGE: no realtime remove notification (silent)
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->key = '';
        $this->is_active = true;

        $this->siatex_variant = 'default';
        $this->category_id = null;

        $this->title = '';
        $this->subtitle = '';

        $this->show_navigation = true;
        $this->show_indicators = true;
        $this->indicator_style = 'dots';

        // ✅ CHANGE: default delay 3000
        $this->delay = 3000;

        $this->layout = 'image-left';
        $this->height = '560px';
        $this->bg_color = '#f5f5f5';

        // cinematic-pro defaults
        $this->motion_duration = 6.5;
        $this->zoom_min = 1.06;
        $this->zoom_max = 1.18;
        $this->pan_strength = 2.0;
        $this->rotate = 0.6;
        $this->fade_ms = 900;

        // ✅ CHANGE: overlay default 0.2
        $this->overlay_opacity = 0.2;

        $this->preview = true;
    }

    public function render()
    {
        $sliders = Slider::query()
            ->where('settings_json->plugin', 'siatex')
            ->orderByDesc('id')
            ->get();

        $taxonomyId = $this->mediaCategoryTaxonomyId();
        $categories = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('name')
            ->get();

        $editing = $this->editingId ? Slider::find($this->editingId) : null;

        $variants = $this->siatexVariantOptions();

        return view('plugins.siatex::livewire.siatex-slider-manager', compact('sliders', 'categories', 'editing', 'variants'));
    }
}