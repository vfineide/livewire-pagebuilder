<?php

namespace Fineide\LivewirePagebuilder;

use Livewire\Attributes\Modelable;
use Livewire\Component;

abstract class BaseBlock extends Component
{
    public $section = [];

    public $index;

    #[Modelable]
    public $content;

    public $editor = false;

    public function mount($index = null, $content = [], $editor = false)
    {
        $this->index = $index;
        $this->content = $content;
        $this->editor = $editor;
        $this->section = $content;
    }
}
