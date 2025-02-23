<?php

use Fineide\LivewirePagebuilder\BaseBlock;

new class extends BaseBlock
{
    public static $metadata = [
        'name' => 'Overskrift',
        'icon' => 'h1',
        'category' => 'Basic',
    ];

    public static $schema = [
        'alignment' => [
            'type' => 'select',
            'label' => 'Justering',
            'options' => [
                ['value' => 'left', 'label' => 'Venstre'],
                ['value' => 'center', 'label' => 'Sentrert'],
                ['value' => 'right', 'label' => 'Høyre'],
            ],
        ],
        'size' => [
            'type' => 'select',
            'label' => 'Størrelse',
            'options' => [
                ['value' => 'small', 'label' => 'Liten'],
                ['value' => 'medium', 'label' => 'Medium'],
                ['value' => 'large', 'label' => 'Stor'],
            ],
        ],
        'title' => [
            'type' => 'input',
            'label' => 'Overskrift',
            'icon' => 'h1',
        ],
        'subtitle' => [
            'type' => 'input',
            'label' => 'Undertittel',
            'icon' => 'h2',
        ],
    ];
};
?>

<div>
    {{-- Editor Mode --}}
    @if($editor)
        <div class="space-y-2 py-4">
            @foreach(static::$schema as $field => $config)
                <x-dynamic-form-field 
                    :field="$field" 
                    :config="$config" 
                    :wire-model="'section.' . $field"
                    wire:change="saveSection"
                />
            @endforeach
        </div>

    {{-- Preview Mode --}}
    @else
        <div 
            x-data="{ isVisible: false }" 
            x-init="
                setTimeout(() => {
                    isVisible = false;
                    setTimeout(() => isVisible = true, 100);
                }, 50)
            "
            x-intersect:enter="isVisible = true"
            x-intersect:leave="isVisible = false"
            :class="{ 
                'translate-y-5 opacity-0': !isVisible, 
                'translate-y-0 opacity-100': isVisible 
            }" 
            class="py-12 transition-all duration-1000 ease-out"
        >
            <div class="container mx-auto px-4">
                <div class="text-{{ $content['alignment'] ?? 'left' }}">
                    @if(isset($content['title']))
                        <h2 class="font-sofia {{ 
                            match($content['size'] ?? 'medium') {
                                'small' => 'text-2xl md:text-3xl',
                                'medium' => 'text-3xl md:text-4xl',
                                'large' => 'text-4xl md:text-5xl',
                            }
                        }} font-medium text-[#282d4f] mb-4">
                            {{ $content['title'] }}
                        </h2>
                    @endif

                    @if(isset($content['subtitle']))
                        <div class="font-sofia text-lg md:text-xl text-[#459bd6]">
                            {{ $content['subtitle'] }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div> 