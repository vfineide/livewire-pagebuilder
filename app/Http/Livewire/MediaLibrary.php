<?php

namespace App\Http\Livewire;

use Livewire\Component;

class MediaLibrary extends Component
{
    public function selectMedia($mediaId)
    {
        $this->skipRender();
        // ... rest of your selection logic
    }
} 