<?php

use Livewire\Volt\Component;

new class extends Component
{
    public $schema;
} 
?>
<div>
<pre class="">
{{ print_r($schema) }}
</pre>

@switch($schema['type'])
    @case('select')
        <flux:select wire:model="{{ $wireModel }}" placeholder="{{ $config['label'] }}"
            wire:change="saveSection"
        >
            @foreach($config['options'] as $option)
                <flux:select.option value="{{ $option['value'] }}">
                    {{ $option['label'] }}
                </flux:select.option>
            @endforeach
        </flux:select>
        @break

    @case('media')
        <livewire:media-library 
            model="{{ $wireModel }}" 
            :multiple="$config['multiple'] ?? false"
        />
        @break

    @case('input')
        <flux:input 
            wire:model="{{ $wireModel }}"
            wire:keydown.debounce.500ms="saveSection"
            label="{{ $config['label'] }}"
        />
        @break

    @case('textarea')
        <flux:textarea 
            wire:model="{{ $wireModel }}"
            wire:keydown.debounce.500ms="saveSection"
            label="{{ $config['label'] }}"
        />
        @break

    @case('repeater')
        <div class="space-y-4">
            <div class="font-medium text-gray-700">{{ $config['label'] }}</div>
            @foreach(data_get($this, $wireModel) ?? [] as $index => $item)
                <div class="bg-gray-50 p-4 rounded-lg relative group">
                    <button wire:click="removeRepeaterItem('{{ $wireModel }}', {{ $index }})"
                            class="absolute right-2 top-2 opacity-0 group-hover:opacity-100 transition-opacity p-1 hover:bg-gray-200 rounded">
                        
                    </button>
                    
                    @foreach($config['fields'] as $subField => $subConfig)
                        <x-field 
                            :field="$subField"
                            :config="$subConfig"
                            :wire-model="$wireModel . '.' . $index . '.' . $subField"
                        />
                    @endforeach
                </div>
            @endforeach
            
            <button wire:click="addRepeaterItem('{{ $wireModel }}')"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                
                Add {{ Str::singular($config['label']) }}
            </button>
        </div>
        @break

    @case('group')
        @foreach($config['fields'] as $subField => $subConfig)
            <x-field 
                :field="$subField"
                :config="$subConfig"
                :wire-model="$wireModel . '.' . $subField"
            />
        @endforeach
        @break
@endswitch 

</div> 