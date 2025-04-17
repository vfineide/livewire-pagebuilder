<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component
{


    public $schema;
    public $index;
    public $content;
    public $model;
    public $values = [];

    public $section;
    public $name;


    public function mount($schema, $index, $name, $content)
    {
        $this->name = $name;
        $this->section = [];
        $this->content = $content;
        $this->section = $content;
        $this->index = $index;
        //dd($this->content);

        //$this->schema = $schema;
        //$this->model = $model;
        //$this->index = $index;
    }

    public function saveSection()
    {
        //dd($this->section);
        
        $this->dispatch('updateSectionContent', $this->index, $this->section);
    }

    public function handleMediaSelected($data)
    {
        $this->section[$this->name] = $data['media'];
        $this->saveSection();
    }

    #[On('media-selected')]
    public function onMediaSelected($data)
    {
        if ($data['fieldName'] === $this->name && $data['blockIndex'] === $this->index) {
            $this->handleMediaSelected($data);
        }
    }

}
?>
<div>


<pre class="hidden">
{{ print_r($schema) }}
</pre>



@switch($schema['type'])
    @case('select')
        <flux:select wire:model="section.{{ $name }}" placeholder="{{ $schema['label'] }}"
            wire:change="saveSection"
        >
            @foreach($schema['options'] as $option)
                <flux:select.option value="{{ $option['value'] }}">
                    {{ $option['label'] }}
                </flux:select.option>
            @endforeach
        </flux:select>
        @break

    @case('media')
        <livewire:media-library 
            model="section.{{ $name }}" 
            :multiple="$schema['multiple'] ?? false"
            :blockIndex="$index"
            :fieldLabel="$schema['label']"
            :content="$section"
        />
        @break

    @case('input')
        <flux:input 
                wire:model="section.{{ $name }}"
            wire:keydown.debounce.500ms="saveSection"
            label="{{ $schema['label'] }}"
        />
        @break

    @case('textarea')
        <flux:textarea 
                wire:model="section.{{ $name }}"
            wire:keydown.debounce.500ms="saveSection"
            label="{{ $schema['label'] }}"
        />
        @break

    @case('richtext')
        <flux:editor
                wire:model="section.{{ $name }}"
            wire:keydown.debounce.500ms="saveSection"
            label="{{ $schema['label'] }}"
        />
        @break

    @case('repeater')
        <div class="space-y-4">
            <div class="font-medium text-gray-700">{{ $schema['label'] }}</div>
            @foreach(data_get($this, $model) ?? [] as $index => $item)
                <div class="bg-gray-50 p-4 rounded-lg relative group">
                    <button wire:click="removeRepeaterItem('{{ $model }}', {{ $index }})"
                            class="absolute right-2 top-2 opacity-0 group-hover:opacity-100 transition-opacity p-1 hover:bg-gray-200 rounded">
                        
                    </button>
                    
                    @foreach($schema['fields'] as $subField => $subConfig)
                        <livewire:fields 
                            :schema="$subConfig"
                            :model="$model . '.' . $index . '.' . $subField"
                            :index="$index"
                        />
                    @endforeach
                </div>
            @endforeach
            
            <button wire:click="addRepeaterItem('{{ $model }}')"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                
                Add {{ Str::singular($schema['label']) }}
            </button>
        </div>
        @break

    @case('group')

            @foreach($schema['fields'] as $subField => $subConfig)
            <livewire:fields 
                :schema="$subConfig"
                :model="$model . '.' . $index . '.' . $subField"
                :index="$index"
            />
        @endforeach

        @break
@endswitch 


</div>