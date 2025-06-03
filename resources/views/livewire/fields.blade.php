<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Cache;


new class extends Component
{

    public $schema;
    public $index;
    public $content;
    public $model;
    public $values = [];
    public $section;
    public $name;

    public $medias;

    public function mount($schema, $index, $name, $content)
    {
        $this->name = $schema['name'];
        $this->section = $content;
        $this->content = $content;
        $this->index = $index;
        $this->schema = $schema;

        // Ensure we have a meta ID for this field
        if (!isset($this->section[$this->name . '.meta'])) {
            $this->section[$this->name . '.meta'] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'createdAt' => now()->toDateTimeString()
            ];
        }
    }

    public function saveSection()
    {
        // Clear the entire cache instead of using tags
        Cache::flush();

        // Ensure we have the latest content before dispatching
        $this->dispatch('updateSectionContent', $this->index, $this->section);
    }

    public function saveField($value)
    {
        // Get the field's meta ID
        $fieldId = $this->section[$this->name . '.meta']['id'];
        $type = $this->schema['type'];
    
        
        // Create a minimal update object with just this field

        if($type == 'switch'){
            $value =  $value ? 1 : 0;
        }

        $update = [
            $this->name => $value,
            $this->name . '.meta' => [
                'id' => $fieldId,
                'updatedAt' => now()->toDateTimeString()
            ]
        ];

     

        // Dispatch the update with the field ID for tracking
        $this->dispatch('updateSectionField', [
            'sectionIndex' => $this->index,
            'fieldId' => $fieldId,
            'fieldName' => $this->name,
            'content' => $update
        ]);
    }

    public function updatedSection($value, $key)
    {
        // When a section value is updated, save just that field
        if ($key === $this->name) {
            $this->saveField($value);
        }
    }

    #[On('media-selected')]
    public function onMediaSelected($data)
    {
        $this->updateMediaField($this->section, $data);
        $this->saveSection();
    }

    private function updateMediaField(&$content, $data)
    {
        
        // If this is an array, recursively search through it
        if (is_array($content)) {
            foreach ($content as $key => &$value) {
                // Check if we found a meta field with matching ID
                if (str_ends_with($key, '.meta') && isset($value['id']) && $value['id'] === $data['id']) {
                    // Get the base field name by removing '.meta'
                    $baseFieldName = substr($key, 0, -5);
                    // Update the corresponding media field
                    $content[$baseFieldName] = $data['media'];
                    return true;
                }
                
                // Recursively search nested arrays (like repeater fields)
                if (is_array($value)) {
                    if ($this->updateMediaField($value, $data)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    public function addRepeaterItem()
    {
        if (!isset($this->section[$this->schema['name']])) {
            $this->section[$this->schema['name']] = [];
        }
        
        // Initialize fields with meta information
        $fields = [];
        foreach ($this->schema['fields'] as $field) {
            $fields[$field['name']] = null;
            $fields[$field['name'] . '.meta'] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'createdAt' => now()->toDateTimeString()
            ];
        }
        
        $this->section[$this->schema['name']][] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'fields' => $fields
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


@case('switch')
<flux:switch
    align="left"
    wire:model.live="section.{{ $schema['name'] }}"
    wire:change="saveSection"
    label="{{ $schema['label'] }}"
/>
@break







@case('color')
<flux:label>{{ $schema['label'] }}</flux:label><br>
<input type="color" 
wire:model.live="section.{{ $schema['name'] }}"
wire:blur="saveSection"
/>

                                    @break

    @case('select')
        <flux:select 
            wire:model="section.{{ $schema['name'] }}" 
            placeholder="{{ $schema['label'] }}" 
            wire:change="saveField($event.target.value)"
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
        <livewire:media-library 
            :fieldName="$schema['name']"
            :fieldLabel="$schema['label']"
            :section="$section"
            :multiple="$schema['multiple'] ?? false"
            :key="'media-library-' . $section[$schema['name'] . '.meta']['id']"
        />
        @break

    @case('input')
        <flux:input 
            wire:model="section.{{ $schema['name'] }}"
            wire:blur="saveField($event.target.value)"
            label="{{ $schema['label'] }}"
        />
        @break

    @case('textarea')
        <flux:textarea 
            wire:model="section.{{ $schema['name'] }}"
            wire:blur="saveField($event.target.value)"
            label="{{ $schema['label'] }}"
        />
        @break

    @case('richtext')
    <div class="max-w-[200px]">
        <flux:editor

            wire:model="section.{{ $schema['name'] }}"
            wire:blur="saveField($event.target.value)"
            label="{{ $schema['label'] }}"
        />
    </div>
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
                        
                        <div class="space-y-4" wire:key="repeater-fields-{{ $item['id'] }}">
                            @foreach($schema['fields'] as $field)
                                @php
                                    $fieldValue = $item['fields'][$field['name']] ?? null;
                                @endphp

                                @switch($field['type'])


@case('switch')
<flux:switch
    align="left"
    wire:model.live="section.{{ $schema['name'] }}.{{ $repeaterIndex }}.fields.{{ $field['name'] }}"
    wire:change="saveSection"
    label="{{ $field['label'] }}"
/>
@break



                                    @case('color')
                                    <input type="color" wire:model="section.{{ $schema['name'] }}.{{ $repeaterIndex }}.fields.{{ $field['name'] }}" wire:change="saveSection" label="{{ $field['label'] }}" />
                                    @break
                                
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
                                            :fieldName="$field['name']"
                                            :fieldLabel="$field['label']"
                                            :section="[
                                                $field['name'] => $fieldValue,
                                                $field['name'] . '.meta' => $item['fields'][$field['name'] . '.meta'] ?? [
                                                    'id' => (string) \Illuminate\Support\Str::uuid(),
                                                    'createdAt' => now()->toDateTimeString()
                                                ]
                                            ]"
                                            :multiple="$field['multiple'] ?? false"
                                            :key="'media-library-' . ($item['fields'][$field['name'] . '.meta']['id'] ?? Str::uuid())"
                                        />
                                        @break

                                    @default
                                        <livewire:fields 
                                            :schema="$field"
                                            :index="$repeaterIndex"
                                            :name="$field['name']"
                                            :content="[
                                                'fields' => [
                                                    $field['name'] => $fieldValue,
                                                    $field['name'] . '.meta' => $item['fields'][$field['name'] . '.meta'] ?? [
                                                        'id' => (string) \Illuminate\Support\Str::uuid(),
                                                        'createdAt' => now()->toDateTimeString()
                                                    ]
                                                ]
                                            ]"
                                            :key="'repeater-field-' . $item['id'] . '-' . $field['name']"
                                        />
                                @endswitch
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            

            <flux:button variant="filled" size="sm" wire:click="addRepeaterItem">
                Add {{ Str::singular($schema['label']) }}
            </flux:button>
    
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