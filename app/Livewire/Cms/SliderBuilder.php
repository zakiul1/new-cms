<?php

namespace App\Livewire\Cms;

use App\Models\Media;
use App\Models\Slide;
use App\Models\Slider;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class SliderBuilder extends Component
{
    // Left list
    public Collection $sliders;

    // Selected slider + slider form
    public ?int $sliderId = null;
    public string $name = '';
    public string $key = '';
    public bool $is_active = true;
    public bool $autoplay = true;
    public int $delay = 6000;
    public string $height = '';

    // Hero (left content) per slider
    public string $hero_kicker = '';
    public string $hero_title = '';
    public string $hero_subtitle = '';

    /** @var array<int, array{text:string,url:string,new_tab:bool,style:string}> */
    public array $hero_buttons = [];

    // UI options
    public bool $show_indicators = true;
    public string $indicator_style = 'dots'; // dots | lines
    public bool $show_navigation = true;
    public string $variant = 'default'; // future: more variants

    // Slides list
    public Collection $slides;

    // Slide form (image-only for now)
    public ?int $slideId = null;
    public ?int $media_id = null;
    public bool $slide_active = true;

    // Media picker
    public bool $showMediaPicker = false;

    public function mount(): void
    {
        $this->refreshSliders();

        if ($this->sliders->count()) {
            $this->selectSlider($this->sliders->first()->id);
        } else {
            $this->slides = collect();
        }
    }

    public function refreshSliders(): void
    {
        $this->sliders = Slider::query()->orderByDesc('id')->get();
    }

    public function selectSlider(int $id): void
    {
        $s = Slider::query()->findOrFail($id);

        $this->sliderId = $s->id;
        $this->name = (string) $s->name;
        $this->key = (string) $s->key;
        $this->is_active = (bool) $s->is_active;

        $settings = (array) ($s->settings_json ?? []);

        // ✅ merge defaults so old sliders won't break
        $settings = array_merge([
            'autoplay' => true,
            'delay' => 6000,
            'height' => '',

            'hero_kicker' => '',
            'hero_title' => '',
            'hero_subtitle' => '',
            'hero_buttons' => [],

            'show_indicators' => true,
            'indicator_style' => 'dots',
            'show_navigation' => true,
            'variant' => 'default',
        ], $settings);

        $this->autoplay = (bool) $settings['autoplay'];
        $this->delay = (int) $settings['delay'];
        $this->height = (string) $settings['height'];

        // hero content
        $this->hero_kicker = (string) $settings['hero_kicker'];
        $this->hero_title = (string) $settings['hero_title'];
        $this->hero_subtitle = (string) $settings['hero_subtitle'];

        $this->hero_buttons = is_array($settings['hero_buttons'])
            ? array_values($settings['hero_buttons'])
            : [];

        // options
        $this->show_indicators = (bool) $settings['show_indicators'];

        $indicator = (string) ($settings['indicator_style'] ?? 'dots');
        $this->indicator_style = in_array($indicator, ['dots', 'lines'], true) ? $indicator : 'dots';

        $this->show_navigation = (bool) $settings['show_navigation'];
        $this->variant = (string) ($settings['variant'] ?? 'default');

        $this->slides = $s->slides()->with('media')->orderBy('sort_order')->get();

        $this->resetSlideForm();
        $this->showMediaPicker = false;
    }


    public function createSlider(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'unique:sliders,key'],
        ]);

        $data['key'] = Str::slug($data['key']);
        $data['is_active'] = true;
        $data['settings_json'] = [
            'autoplay' => true,
            'delay' => 6000,
            'height' => '',

            // hero defaults
            'hero_kicker' => '',
            'hero_title' => '',
            'hero_subtitle' => '',
            'hero_buttons' => [],

            // ui defaults
            'show_indicators' => true,
            'indicator_style' => 'dots',
            'show_navigation' => true,
            'variant' => 'default',
        ];

        $s = Slider::create($data);

        $this->refreshSliders();
        $this->selectSlider($s->id);

        Notification::make()
            ->title('Slider created')
            ->body('Now add slides and update the hero content.')
            ->success()
            ->send();
    }

    public function saveSlider(): void
    {
        if (!$this->sliderId)
            return;

        $s = Slider::query()->findOrFail($this->sliderId);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'unique:sliders,key,' . $s->id],
            'is_active' => ['boolean'],
            'autoplay' => ['boolean'],
            'delay' => ['integer', 'min:100', 'max:600000'],
            'height' => ['nullable', 'string', 'max:50'],

            'hero_kicker' => ['nullable', 'string', 'max:80'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:255'],

            'show_indicators' => ['boolean'],
            'indicator_style' => ['required', 'in:dots,lines'],
            'show_navigation' => ['boolean'],
            'variant' => ['nullable', 'string', 'max:40'],
        ]);

        // Validate buttons (simple + safe)
        $buttons = [];
        foreach ($this->hero_buttons as $btn) {
            $text = trim((string) data_get($btn, 'text', ''));
            $url = trim((string) data_get($btn, 'url', ''));
            $style = (string) data_get($btn, 'style', 'primary');

            if ($text === '' && $url === '') {
                continue;
            }

            if ($text === '' || $url === '') {
                $this->addError('hero_buttons', 'Each button needs both text and URL.');
                return;
            }

            $buttons[] = [
                'text' => mb_substr($text, 0, 80),
                'url' => mb_substr($url, 0, 255),
                'new_tab' => (bool) data_get($btn, 'new_tab', false),
                'style' => in_array($style, ['primary', 'secondary', 'outline'], true) ? $style : 'primary',
            ];
        }

        $s->update([
            'name' => $validated['name'],
            'key' => Str::slug($validated['key']),
            'is_active' => (bool) $validated['is_active'],
            'settings_json' => [
                'autoplay' => (bool) $validated['autoplay'],
                'delay' => (int) $validated['delay'],
                'height' => (string) ($validated['height'] ?? ''),

                'hero_kicker' => (string) ($validated['hero_kicker'] ?? ''),
                'hero_title' => (string) ($validated['hero_title'] ?? ''),
                'hero_subtitle' => (string) ($validated['hero_subtitle'] ?? ''),
                'hero_buttons' => $buttons,

                'show_indicators' => (bool) $validated['show_indicators'],
                'indicator_style' => (string) $validated['indicator_style'],
                'show_navigation' => (bool) $validated['show_navigation'],
                'variant' => (string) ($validated['variant'] ?? 'default'),
            ],
        ]);

        $this->refreshSliders();
        $this->selectSlider($s->id);

        Notification::make()
            ->title('Slider saved')
            ->success()
            ->send();
    }

    public function deleteSlider(int $id): void
    {
        $s = Slider::query()->findOrFail($id);
        $s->slides()->delete();
        $s->delete();

        $this->sliderId = null;
        $this->refreshSliders();

        if ($this->sliders->count()) {
            $this->selectSlider($this->sliders->first()->id);
        } else {
            $this->slides = collect();
        }

        Notification::make()
            ->title('Slider deleted')
            ->success()
            ->send();
    }

    public function addHeroButton(): void
    {
        $this->hero_buttons[] = [
            'text' => '',
            'url' => '',
            'new_tab' => false,
            'style' => 'primary',
        ];
    }

    public function removeHeroButton(int $index): void
    {
        if (!isset($this->hero_buttons[$index])) {
            return;
        }

        unset($this->hero_buttons[$index]);
        $this->hero_buttons = array_values($this->hero_buttons);
    }

    // ---------- Slides ----------
    public function resetSlideForm(): void
    {
        $this->slideId = null;
        $this->media_id = null;
        $this->slide_active = true;
    }

    public function editSlide(int $id): void
    {
        $slide = Slide::query()->with('media')->findOrFail($id);

        $this->slideId = $slide->id;
        $this->media_id = $slide->media_id;
        $this->slide_active = (bool) $slide->is_active;
    }

    public function saveSlide(): void
    {
        if (!$this->sliderId)
            return;

        $isUpdate = (bool) $this->slideId;

        $this->validate([
            'media_id' => ['required', 'integer', 'exists:media,id'],
            'slide_active' => ['boolean'],
        ]);

        $slider = Slider::query()->findOrFail($this->sliderId);

        if ($this->slideId) {
            $slide = $slider->slides()->findOrFail($this->slideId);
        } else {
            $max = (int) $slider->slides()->max('sort_order');
            $slide = $slider->slides()->make(['sort_order' => $max + 1]);
        }

        $slide->fill([
            'media_id' => $this->media_id,
            // slide-level text/buttons deprecated in favor of per-slider hero content
            'title' => null,
            'subtitle' => null,
            'button_text' => null,
            'button_url' => null,
            'button_new_tab' => false,
            'is_active' => (bool) $this->slide_active,
        ])->save();

        $this->selectSlider($slider->id);
        $this->resetSlideForm();
        $this->showMediaPicker = false;

        Notification::make()
            ->title($isUpdate ? 'Slide updated' : 'Slide created')
            ->success()
            ->send();
    }

    public function deleteSlide(int $id): void
    {
        if (!$this->sliderId)
            return;

        $slider = Slider::query()->findOrFail($this->sliderId);
        $slider->slides()->whereKey($id)->delete();

        // re-normalize sort_order
        $slides = $slider->slides()->orderBy('sort_order')->get();
        foreach ($slides as $i => $s) {
            $s->update(['sort_order' => $i + 1]);
        }

        $this->selectSlider($slider->id);

        Notification::make()
            ->title('Slide deleted')
            ->success()
            ->send();
    }

    public function moveSlide(int $id, string $dir): void
    {
        if (!$this->sliderId)
            return;

        $slider = Slider::query()->findOrFail($this->sliderId);
        $slide = $slider->slides()->findOrFail($id);

        $current = (int) $slide->sort_order;
        $targetOrder = $dir === 'up' ? $current - 1 : $current + 1;

        $swap = $slider->slides()->where('sort_order', $targetOrder)->first();
        if (!$swap)
            return;

        $swap->update(['sort_order' => $current]);
        $slide->update(['sort_order' => $targetOrder]);

        $this->selectSlider($slider->id);
    }

    // ---------- Media picker ----------
    public function openMediaPicker(): void
    {
        $this->showMediaPicker = true;
    }

    #[On('media-library-apply')]
    public function handleMediaLibraryApply(array $ids, string $statePath = ''): void
    {
        if ($statePath !== 'slider-slide-image') {
            return;
        }

        $id = (int) ($ids[0] ?? 0);
        if ($id > 0) {
            $this->media_id = $id;
        }

        $this->showMediaPicker = false;
    }

    public function render()
    {
        $selectedMedia = $this->media_id ? Media::query()->find($this->media_id) : null;

        return view('livewire.cms.slider-builder', [
            'selectedMedia' => $selectedMedia,
        ]);
    }
}