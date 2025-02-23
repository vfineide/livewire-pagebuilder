<?php

namespace Fineide\LivewirePagebuilder;

use Livewire\Component;
use Illuminate\Support\Facades\File;

class PageBuilder extends Component
{
    public $page;
    public $sections = [];
    public $listeners = ['updateSectionContent'];

    public function mount($id)
    {
        $this->page = \App\Models\Page::findOrFail($id);
        $this->sections = $this->page->sections ?? [];
    }

    // ... copy all the methods from your original code ...

    public function render()
    {
        return view('livewire-pagebuilder::pagebuilder');
    }
} 