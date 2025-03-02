<?php

use Fineide\LivewirePagebuilder\BaseBlock;

new class extends BaseBlock
{
    public static $metadata = [
        'name' => 'Tekst og bilde',
        'icon' => 'photo',
        'category' => 'Basic',
    ];

    public static $schema = [
        'layout' => [
            'type' => 'select',
            'label' => 'Layout',
            'options' => [
                ['value' => 'image-left', 'label' => 'Bilde til venstre'],
                ['value' => 'image-right', 'label' => 'Bilde til høyre'],
                ['value' => 'image-top', 'label' => 'Bilde på topp'],
                ['value' => 'image-bottom', 'label' => 'Bilde på bunn'],
            ],
        ],
        'spacing' => [
            'type' => 'select',
            'label' => 'Spacing',
            'options' => [
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'compact', 'label' => 'Kompakt'],
                ['value' => 'wide', 'label' => 'Vid'],
            ],
        ],
        'background' => [
            'type' => 'select',
            'label' => 'Bakgrunn',
            'options' => [
                ['value' => 'white', 'label' => 'Hvit'],
                ['value' => 'light', 'label' => 'Lys grå'],
                ['value' => 'dark', 'label' => 'Mørk'],
            ],
        ],
        'image' => [
            'type' => 'media',
            'label' => 'Bilde',
            'multiple' => false,
        ],
        'title' => [
            'type' => 'input',
            'label' => 'Overskrift',
        ],
        'content' => [
            'type' => 'textarea',
            'label' => 'Innhold',
        ],
        'button' => [
            'type' => 'group',
            'fields' => [
                'text' => [
                    'type' => 'input',
                    'label' => 'Knappetekst',
                ],
                'url' => [
                    'type' => 'input',
                    'label' => 'URL',
                ],
            ],
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
        @php
            $spacing = match($content['spacing'] ?? 'normal') {
                'compact' => 'py-8',
                'wide' => 'py-24',
                default => 'py-16'
            };

            $background = match($content['background'] ?? 'white') {
                'light' => 'bg-gray-50',
                'dark' => 'bg-[#282d4f] text-white',
                default => 'bg-white'
            };

            $layout = $content['layout'] ?? 'image-left';
            $isHorizontal = in_array($layout, ['image-left', 'image-right']);
        @endphp

        <div class="{{ $background }} {{ $spacing }}"
            x-data="{ isVisible: false }" 
            x-init="
                setTimeout(() => {
                    isVisible = false;
                    setTimeout(() => isVisible = true, 100);
                }, 50)
            "
            x-intersect:enter="isVisible = true"
            x-intersect:leave="isVisible = false"
        >
            <div class="container mx-auto px-4">
                <div class="@if($isHorizontal) grid md:grid-cols-2 gap-8 md:gap-12 items-center @else space-y-8 @endif">
                    
                    {{-- Image Section --}}
                    @if(isset($content['image']))
                        @php
                        $image = isset($content['image']) && is_numeric($content['image']) ? App\Models\Media::find($content['image']) : null;

                            $imageOrder = match($layout) {
                                'image-right' => 'order-2',
                                'image-bottom' => 'order-2',
                                default => 'order-1'
                            };
                        @endphp
                        
                        <div class="{{ $imageOrder }}" 
                            :class="{ 
                                'translate-y-5 opacity-0': !isVisible, 
                                'translate-y-0 opacity-100': isVisible 
                            }"
                            style="transition: all 1s ease-out {{ $imageOrder === 'order-2' ? '0.2s' : '0s' }}"
                        >
                            @if($image)
                                <img 
                                    src="{{ Storage::disk('s3')->url($image->path) }}" 
                                    alt="{{ $content['title'] ?? 'Section image' }}"
                                    class="w-full rounded-lg shadow-lg"
                                >
                            @endif
                        </div>
                    @endif

                    {{-- Content Section --}}
                    <div class="{{ $layout === 'image-right' ? 'order-1' : 'order-2' }}"
                        :class="{ 
                            'translate-y-5 opacity-0': !isVisible, 
                            'translate-y-0 opacity-100': isVisible 
                        }"
                        style="transition: all 1s ease-out {{ $layout === 'image-right' ? '0s' : '0.2s' }}"
                    >
                        @if(isset($content['title']))
                            <h2 class="font-sofia text-3xl md:text-4xl font-medium mb-6 {{ $background === 'dark' ? 'text-white' : 'text-[#282d4f]' }}">
                                {{ $content['title'] }}
                            </h2>
                        @endif

                        @if(isset($content['content']))
                            <div class="prose max-w-none {{ $background === 'dark' ? 'prose-invert' : '' }}">
                                {{ $content['content'] }}
                            </div>
                        @endif

                        @if(isset($content['button']) && isset($content['button']['text']) && isset($content['button']['url']))
                            <a 
                                href="{{ $content['button']['url'] }}" 
                                class="mt-8 inline-flex bg-[#459bd6] text-white uppercase rounded-full px-8 py-4 text-sm font-medium duration-300 hover:bg-[#b4a599] tracking-wider"
                            >
                                {{ $content['button']['text'] }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div> 