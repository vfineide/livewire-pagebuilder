<?php

namespace Fineide\LivewirePagebuilder\View\Components;

use Illuminate\View\Component;

class BlockEditor extends Component
{
    public $schema;
    public $section;
    public $wireKey;

    public function __construct($schema, $section, $wireKey)
    {
        $this->schema = $schema;
        $this->section = $section;
        $this->wireKey = $wireKey;
    }

    public function render()
    {
        return view('livewire-pagebuilder::components.block-editor');
    }
} 