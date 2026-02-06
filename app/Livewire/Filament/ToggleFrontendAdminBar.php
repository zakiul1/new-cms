<?php

namespace App\Livewire\Filament;

use App\Cms\Core\SettingsRepository;
use Filament\Notifications\Notification;
use Livewire\Component;

class ToggleFrontendAdminBar extends Component
{
    public bool $enabled = true;

    public function mount(SettingsRepository $settings): void
    {
        $this->enabled = (bool) $settings->get('core', 'frontend_admin_bar_enabled', true);
    }

    public function toggle(SettingsRepository $settings): void
    {
        $this->enabled = !$this->enabled;

        $settings->set('core', 'frontend_admin_bar_enabled', $this->enabled);

        Notification::make()
            ->success()
            ->title('Frontend admin bar: ' . ($this->enabled ? 'ON' : 'OFF'))
            ->send();
    }

    public function render()
    {
        return view('livewire.filament.toggle-frontend-admin-bar');
    }
}