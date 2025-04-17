<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Media;
use Livewire\Attributes\On;

new cla ss extends Component
{   
    public $schema;
    public $section;
    public $wireKey;
}
?>

<div class="space-y-2 py-4">
    @foreach($schema as $field => $config)
        <div class="space-y-1">
            @switch($config['type'])
                @case('select')
                    <flux:select 
                        wire:model="section.{{ $field }}" 
                        placeholder="{{ $config['label'] }}"
                        wire:change="saveSection"
                    >
                        @foreach($config['options'] as $option)
                            <flux:select.option value="{{ $option['value'] }}">
                                {{ $option['label'] }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @break

                @case('input')
                    <flux:input 
                        wire:model="section.{{ $field }}"
                        wire:keydown.debounce.500ms="saveSection"
                        label="{{ $config['label'] }}"
                    />
                    @break

                @case('textarea')
                    <flux:textarea 
                        wire:model="section.{{ $field }}"
                        wire:keydown.debounce.500ms="saveSection"
                        label="{{ $config['label'] }}"
                    />
                    @break

                @case('media')
                    <livewire:media-library 
                        model="section.{{ $field }}" 
                        :multiple="$config['multiple'] ?? false"
                    />
                    @break
            @endswitch
        </div>
    @endforeach
</div> 