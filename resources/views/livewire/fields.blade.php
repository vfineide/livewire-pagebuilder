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
        $this->name = $schema['name'];
        $this->section = $content;
        $this->content = $content;
        $this->index = $index;
        $this->schema = $schema;
    }

    public function saveSection()
    {
        $this->dispatch('updateSectionContent', $this->index, $this->section);
    }



    #[On('media-selected')]
    public function onMediaSelected($data)
    {
        if ($data['repeaterId']) {
            // Handle repeater field media
            $this->updateRepeaterFieldMedia($data);
        } else {
            // Handle regular field media
            $this->updateRegularFieldMedia($data);
        }
        
        $this->saveSection();
    }

    private function updateRepeaterFieldMedia($data)
    {
        // Find the repeater item by ID and update its media field
        if (isset($this->section[$this->schema['name']])) {
            foreach ($this->section[$this->schema['name']] as &$item) {
                if ($item['id'] === $data['repeaterId']) {
                    if (!isset($item['fields'])) {
                        $item['fields'] = [];
                    }
                    $item['fields'][$data['fieldName']] = $data['media'];
                    break;
                }
            }
        }
    }

    private function updateRegularFieldMedia($data)
    {
        // Update regular field media
        $this->section[$this->schema['name']] = $data['media'];
    }

    public function addRepeaterItem()
    {
        if (!isset($this->section[$this->schema['name']])) {
            $this->section[$this->schema['name']] = [];
        }
        
        $this->section[$this->schema['name']][] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'fields' => []
        ];
        $this->saveSection();
    }

    public function removeRepeaterItem($name, $index)
    {
        array_splice($this->section[$name], $index, 1);
        
        $this->section[$name] = array_values($this->section[$name]);
        
        $this->saveSection();
    }
    
}
?>
<div>



@switch($schema['type'])
    @case('select')
        <flux:select 
            wire:model="section.{{ $schema['name'] }}" 
            placeholder="{{ $schema['label'] }}" 
            wire:change="saveSection"
        >
            @foreach($schema['options'] as $option)
                <flux:select.option 
                    value="{{ $option['value'] }}" 
                    wire:key="option-{{ $index }}-{{ $schema['name'] }}-{{ $option['value'] }}"
                >
                    {{ $option['label'] }}
                </flux:select.option>
            @endforeach
        </flux:select>
        @break

    @case('media')
        @if($schema['type'] === 'repeater')
            <livewire:media-library 
                model="section.{{ $schema['name'] }}.{{ $repeaterIndex }}.fields.{{ $field['name'] }}"
                :multiple="$field['multiple'] ?? false"
                :blockIndex="$index"
                :fieldLabel="$field['label']"
                :content="['fields' => [$field['name'] => $fieldValue]]"
                :sectionId="$section['id'] ?? null"
                :repeaterId="$item['id']"
                :key="'media-library-' . $item['id'] . '-' . $field['name']"
            />
        @else
            <livewire:media-library 
                model="section.{{ $schema['name'] }}"
                :multiple="$schema['multiple'] ?? false"
                :blockIndex="$index"
                :fieldLabel="$schema['label']"
                :content="$section"
                :sectionId="$section['id'] ?? null"
                :key="'media-library-' . ($section['id'] ?? '') . '-' . $schema['name']"
            />
        @endif
        @break

    @case('input')
        <flux:input 
            wire:model="section.{{ $schema['name'] }}"
            wire:keydown.debounce.500ms="saveSection"
            label="{{ $schema['label'] }}"
        />
        @break

    @case('textarea')
        <flux:textarea 
            wire:model="section.{{ $schema['name'] }}"
            wire:keydown.debounce.500ms="saveSection"
            label="{{ $schema['label'] }}"
        />
        @break

    @case('richtext')
        <flux:editor
            wire:model="section.{{ $schema['name'] }}"
            wire:keydown.debounce.500ms="saveSection"
            label="{{ $schema['label'] }}"
        />
        @break

      @case('repeater')
        <div class="space-y-4" wire:key="repeater-wrapper-{{ $schema['name'] }}">
            <div class="font-medium text-gray-700">{{ $schema['label'] }}</div>
            @if(!isset($section[$schema['name']]))
                @php $section[$schema['name']] = []; @endphp
            @endif
            
            <div wire:key="repeater-items-{{ $schema['name'] }}">
                @foreach($section[$schema['name']] as $repeaterIndex => $item)
                    <div 
                        class="bg-gray-50 p-4 rounded-lg relative group mb-4"
                        wire:key="repeater-item-{{ $item['id'] }}"
                    >
                        <button 
                            type="button"
                            wire:click="removeRepeaterItem('{{ $schema['name'] }}', {{ $repeaterIndex }})"
                            class="absolute right-2 top-2 opacity-0 group-hover:opacity-100 transition-opacity p-1 hover:bg-gray-200 rounded"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <div wire:key="repeater-fields-{{ $item['id'] }}">
                            @foreach($schema['fields'] as $field)
                                @php
                                    $fieldValue = $item['fields'][$field['name']] ?? null;
                                @endphp

                                @switch($field['type'])
                                    @case('input')
                                        <flux:input 
                                            wire:model="section.{{ $schema['name'] }}.{{ $repeaterIndex }}.fields.{{ $field['name'] }}"
                                            wire:change="saveSection"
                                            label="{{ $field['label'] }}"
                                        />
                                        @break

                                    @case('textarea')
                                        <flux:textarea 
                                            wire:model="section.{{ $schema['name'] }}.{{ $repeaterIndex }}.fields.{{ $field['name'] }}"
                                            wire:change="saveSection"
                                            label="{{ $field['label'] }}"
                                        />
                                        @break

                                    @case('richtext')
                                        <flux:editor
                                            wire:model="section.{{ $schema['name'] }}.{{ $repeaterIndex }}.fields.{{ $field['name'] }}"
                                            wire:change="saveSection"
                                            label="{{ $field['label'] }}"
                                        />
                                        @break

                                    @case('media')
                                        <livewire:media-library 
                                            model="section.{{ $schema['name'] }}.{{ $repeaterIndex }}.fields.{{ $field['name'] }}"
                                            :multiple="$field['multiple'] ?? false"
                                            :blockIndex="$index"
                                            :fieldLabel="$field['label']"
                                            :content="['fields' => [$field['name'] => $fieldValue]]"
                                            :sectionId="$section['id'] ?? null"
                                            :repeaterId="$item['id']"
                                            :key="'media-library-' . $item['id'] . '-' . $field['name']"
                                        />
                                        @break

                                    @default
                                        <livewire:fields 
                                            :schema="$field"
                                            :index="$repeaterIndex"
                                            :name="$field['name']"
                                            :content="['fields' => [$field['name'] => $fieldValue]]"
                                            :key="'repeater-field-' . $item['id'] . '-' . $field['name']"
                                        />
                                @endswitch
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            
            <button 
                type="button"
                wire:click="addRepeaterItem"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
            >
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