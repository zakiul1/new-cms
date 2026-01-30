<?php

namespace App\Livewire\Cms;

use App\Models\Media;
use App\Models\Slide;
use App\Models\Slider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
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

    // Slides list
    public Collection $slides;

    // Slide form
    public ?int $slideId = null;
    public ?int $media_id = null;
    public string $slide_title = '';
    public string $slide_subtitle = '';
    public string $button_text = '';
    public string $button_url = '';
    public bool $button_new_tab = false;
    public bool $slide_active = true;

    // Media picker
    public string $mediaSearch = '';
    public bool $showMediaPicker = false;

    public function mount(): void
    {
        $this->refreshSliders();

        // auto-open first slider if exists
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
        $this->autoplay = (bool) ($settings['autoplay'] ?? true);
        $this->delay = (int) ($settings['delay'] ?? 6000);
        $this->height = (string) ($settings['height'] ?? '');

        $this->slides = $s->slides()->with('media')->orderBy('sort_order')->get();

        $this->resetSlideForm();
        $this->showMediaPicker = false;
    }

    public function createSlider(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'key'  => ['required', 'string', 'max:255', 'unique:sliders,key'],
        ]);

        $data['key'] = Str::slug($data['key']);
        $data['is_active'] = true;
        $data['settings_json'] = [
            'autoplay' => true,
            'delay' => 6000,
            'height' => '',
        ];

        $s = Slider::create($data);

        $this->refreshSliders();
        $this->selectSlider($s->id);
    }

    public function saveSlider(): void
    {
        if (!$this->sliderId) return;

        $s = Slider::query()->findOrFail($this->sliderId);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'key'  => ['required', 'string', 'max:255', 'unique:sliders,key,' . $s->id],
            'is_active' => ['boolean'],
            'autoplay' => ['boolean'],
            'delay' => ['integer', 'min:100', 'max:600000'],
            'height' => ['nullable', 'string', 'max:50'],
        ]);

        $s->update([
            'name' => $validated['name'],
            'key' => Str::slug($validated['key']),
            'is_active' => (bool) $validated['is_active'],
            'settings_json' => [
                'autoplay' => (bool) $validated['autoplay'],
                'delay' => (int) $validated['delay'],
                'height' => (string) ($validated['height'] ?? ''),
            ],
        ]);

        $this->refreshSliders();
        $this->selectSlider($s->id);
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
    }

    // ---------- Slides ----------
    public function resetSlideForm(): void
    {
        $this->slideId = null;
        $this->media_id = null;
        $this->slide_title = '';
        $this->slide_subtitle = '';
        $this->button_text = '';
        $this->button_url = '';
        $this->button_new_tab = false;
        $this->slide_active = true;
    }

    public function editSlide(int $id): void
    {
        $slide = Slide::query()->with('media')->findOrFail($id);

        $this->slideId = $slide->id;
        $this->media_id = $slide->media_id;
        $this->slide_title = (string) $slide->title;
        $this->slide_subtitle = (string) $slide->subtitle;
        $this->button_text = (string) $slide->button_text;
        $this->button_url = (string) $slide->button_url;
        $this->button_new_tab = (bool) $slide->button_new_tab;
        $this->slide_active = (bool) $slide->is_active;
    }

    public function saveSlide(): void
    {
        if (!$this->sliderId) return;

        $rules = [
            'media_id' => ['required', 'integer', 'exists:media,id'],
            'slide_title' => ['nullable', 'string', 'max:255'],
            'slide_subtitle' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'button_new_tab' => ['boolean'],
            'slide_active' => ['boolean'],
        ];

        // URL required only if text exists
        if (filled($this->button_text)) {
            $rules['button_url'][] = 'required';
        }

        $this->validate($rules);

        $slider = Slider::query()->findOrFail($this->sliderId);

        if ($this->slideId) {
            $slide = $slider->slides()->findOrFail($this->slideId);
        } else {
            $max = (int) $slider->slides()->max('sort_order');
            $slide = $slider->slides()->make(['sort_order' => $max + 1]);
        }

        $slide->fill([
            'media_id' => $this->media_id,
            'title' => $this->slide_title,
            'subtitle' => $this->slide_subtitle,
            'button_text' => $this->button_text,
            'button_url' => $this->button_url,
            'button_new_tab' => (bool) $this->button_new_tab,
            'is_active' => (bool) $this->slide_active,
        ])->save();

        $this->selectSlider($slider->id);
        $this->resetSlideForm();
        $this->showMediaPicker = false;
    }

    public function deleteSlide(int $id): void
    {
        if (!$this->sliderId) return;

        $slider = Slider::query()->findOrFail($this->sliderId);
        $slider->slides()->whereKey($id)->delete();

        // re-normalize sort_order
        $slides = $slider->slides()->orderBy('sort_order')->get();
        foreach ($slides as $i => $s) {
            $s->update(['sort_order' => $i + 1]);
        }

        $this->selectSlider($slider->id);
    }

    // simple reorder (no extra packages)
    public function moveSlide(int $id, string $dir): void
    {
        if (!$this->sliderId) return;

        $slider = Slider::query()->findOrFail($this->sliderId);
        $slide = $slider->slides()->findOrFail($id);

        $current = (int) $slide->sort_order;
        $targetOrder = $dir === 'up' ? $current - 1 : $current + 1;

        $swap = $slider->slides()->where('sort_order', $targetOrder)->first();
        if (!$swap) return;

        $swap->update(['sort_order' => $current]);
        $slide->update(['sort_order' => $targetOrder]);

        $this->selectSlider($slider->id);
    }

    // ---------- Media picker ----------
    public function openMediaPicker(): void
    {
        $this->showMediaPicker = true;
    }

    public function pickMedia(int $id): void
    {
        $this->media_id = $id;
        $this->showMediaPicker = false;
    }

    public function getMediaResultsProperty()
    {
        $q = trim($this->mediaSearch);

        return Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->when($q !== '', function ($query) use ($q) {
                $s = '%' . $q . '%';
                $query->where(function ($qq) use ($s) {
                    $qq->where('title', 'like', $s)
                       ->orWhere('original_filename', 'like', $s);
                });
            })
            ->orderByDesc('id')
            ->limit(24)
            ->get();
    }

    public function render()
    {
        $selectedMedia = $this->media_id ? Media::query()->find($this->media_id) : null;

        return view('livewire.cms.slider-builder', [
            'selectedMedia' => $selectedMedia,
            'mediaResults' => $this->mediaResults,
        ]);
    }
}
