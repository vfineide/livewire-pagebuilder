<?php

namespace Fineide\LivewirePagebuilder;

use Livewire\Component;

abstract class BaseBlock extends Component
{
    public $section = [];
    public $index;
    public $content;
    public $editor = false;

    public function mount($index = null, $content = [], $editor = false)
    {
        $this->index = $index;
        $this->content = $content;
        $this->editor = $editor;
        $this->section = $content;
    }

    public function saveSection()
    {
        $this->dispatch('updateSectionContent', $this->index, $this->section);
    }
} 