<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Page;
use Illuminate\Support\Facades\File;
use App\Models\Media;

new #[Layout('components.layouts.app.pagebuilder')] class extends Component
{
    public $page;
    public $sections = [];
    public $listeners = ['updateSectionContent', 'updateSectionField', 'selectedMedia'];

    public function mount($id)
    {
        $this->page = Page::findOrFail($id);
        //dd($this->page->sections);
       // $this->sections = $this->page->sections ?? [];
           // 1) grab your buttons once
    $this->editorButtons = $this->getEditorButtons();

    // 2) grab your sections
    $this->sections = collect($this->page->sections ?? [])->map(function($section) {
        // and attach the schema upfront
        $section['schema'] = $this->editorButtons[$section['type']]['schema'] ?? [];
        return $section;
    })->toArray();
    }


    public function addSection($type, $name)
    {
        $buttons = $this->getEditorButtons();
        if (!isset($buttons[$type])) {
            throw new \Exception("Unknown section type: {$type}");
        }

        // Initialize content array with meta information for each field
        $content = [];
        foreach ($buttons[$type]['schema'] as $field) {
            $content[$field['name']] = null;
            $content[$field['name'] . '.meta'] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'createdAt' => now()->toDateTimeString()
            ];
        }
 
        $this->sections[] = [
            'id' => uniqid(),
            'name' => $name,
            'type' => $type,
            'content' => $content,
            'namespace' => $buttons[$type]['namespace'],
            'schema' => $buttons[$type]['schema']
        ];
    }

    public function removeSection($index)
    {
        unset($this->sections[$index]);
        $this->sections = array_values($this->sections);
        $this->savePage();
    }


    public function updateSectionContent($index, $content)
    {
        // Convert media objects to their IDs or necessary data
        $processedContent = collect($content)->map(function($value, $key) {
            if (is_object($value) && get_class($value) === Media::class) {
                return [
                    'id' => $value->id,
                    'path' => $value->path,
                    'name' => $value->name
                ];
            }
            return $value;
        })->toArray();

        // Ensure we preserve existing content while updating
        $existingContent = $this->sections[$index]['content'] ?? [];
        
        // Merge the new content with existing content, preserving all fields
        $this->sections[$index]['content'] = array_merge(
            $existingContent,
            array_filter($processedContent, function($value) {
                return $value !== null;
            })
        );
        
        $this->savePage();
    }

    public function updateSectionField($data)
    {
        $sectionIndex = $data['sectionIndex'];
        $fieldId = $data['fieldId'];
        $fieldName = $data['fieldName'];
        $content = $data['content'];

        // Ensure the section exists
        if (!isset($this->sections[$sectionIndex])) {
            return;
        }

        // Update only the specific field
        $this->sections[$sectionIndex]['content'] = array_merge(
            $this->sections[$sectionIndex]['content'] ?? [],
            $content
        );

        $this->savePage();
    }

    public function savePage()
    {
       // dd($this->sections);
        $this->page->sections = $this->sections;
        $this->page->save();
       /// dd($this->page->sections);
    }

// In your PageBuilder component
public function moveSection(array $items)
{
    $orderMap = collect($items)->pluck('order', 'value');

    $this->sections = collect($this->sections)
        ->sortBy(fn($section) => $orderMap->get($section['id']))
        ->values()
        ->toArray();

    $this->savePage();
}

    public function duplicateSection($index)
    {
        if (!isset($this->sections[$index])) {
            return;
        }

        $section = $this->sections[$index];
        
        // Create a deep copy of the section
        $duplicatedSection = $section;
        
        // Generate new ID for the section
        $duplicatedSection['id'] = uniqid();
        
        // Generate new UUIDs for all content fields
        if (isset($duplicatedSection['content'])) {
            foreach ($duplicatedSection['content'] as $key => $value) {
                if (str_ends_with($key, '.meta') && isset($value['id'])) {
                    $duplicatedSection['content'][$key]['id'] = (string) \Illuminate\Support\Str::uuid();
                    $duplicatedSection['content'][$key]['createdAt'] = now()->toDateTimeString();
                }
            }
        }

        // Insert the duplicated section after the original
        array_splice($this->sections, $index + 1, 0, [$duplicatedSection]);
        
        $this->savePage();
    }

    public function getEditorButtons()
    {
        $buttons = [];
        
        // Search paths
        $paths = [
            resource_path('views/livewire/blocks') => 'blocks.'                    // Project path
        ];

        foreach ($paths as $componentsPath => $namespace) {
            if (!File::exists($componentsPath)) {
                \Log::info("Path does not exist: " . $componentsPath);
                continue;
            }
            \Log::info("Checking path: " . $componentsPath);
            
            $files = File::files($componentsPath);
            \Log::info("Found " . count($files) . " files");

            foreach ($files as $file) {
                $content = file_get_contents($file->getPathname());
                $type = str_replace('.blade.php', '', $file->getFilename());
                
                // Extract both metadata and schema
                if (preg_match('/static\s+\$metadata\s*=\s*(\[.*?\]);/s', $content, $metadataMatches) &&
                    preg_match('/public\s+\$schema\s*=\s*(\[.*?\]);/s', $content, $schemaMatches)) {
                    $metadata = eval('return ' . $metadataMatches[1] . ';');
                    $schema = eval('return ' . $schemaMatches[1] . ';');
                    $metadata['namespace'] = $namespace;
                    $metadata['schema'] = $schema; // Add schema to metadata
                    $buttons[$type] = $metadata;
                }
            }
        }

        return $buttons;
    }
}; ?>


<div  x-data="{ 
    sections: @entangle('sections'), 
    previewMode: 'desktop'
}" x-init="
    $store.sectionOrders = new Map();
    sections.forEach((section, index) => {
        $store.sectionOrders.set(section.id, index);
    });
">


<script src="https://cdnjs.cloudflare.com/ajax/libs/filepond/4.32.6/filepond.js" integrity="sha512-9NomenG8ZkuctRQaDSN74Y0kyM2+1FGJTunuSfTFqif+vRrDZM2Ct0Ynp3CIbMNUQOWxd5RCyXexZzlz7KvUcw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/filepond/4.32.6/filepond.css" integrity="sha512-lBRULj1QwuG+xVoiKYx4nmB+CqtaTUs3J21JnY/GiDdYMRfX1i2pcRGbhkGF7hCZJz5e+rV4jjlmSYFIwZARsQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-type@1.2.9/dist/filepond-plugin-file-validate-type.min.js"></script>


<style>
    [data-flux-main] {
        padding: 0 !important;
    }

    #flux-sidebar {
        display: none;
    }

    .section-item {
        order: var(--section-order, 0);
    }
</style>

    <div class="flex">
        <!-- Editor Column (1/2) -->
        <div class="w-1/4 p-4 bg-gray-100 overflow-y-scroll h-screen space-y-4 flex flex-col">

            <div>
            <flux:button variant="ghost" inset="left" href="/admin/pages">{{__('Back')}}</flux:button>
            </div>
            <h2 class="text-xl font-bold mb-4">{{__('Page Builder')}}</h2>


            <h2 class="text-sm text-gray-500 font-bold mb-2">{{__('Add sections')}}</h2>

            <div class="mb-4 grid grid-cols-2 gap-2">
                @foreach($this->getEditorButtons() as $type => $button)
                    <button wire:key="add-section-{{ $type }}" wire:click="addSection('{{ $type }}', '{{ $button['name'] }}')" class="bg-white border border-gray-300 hover:border-blue-500 text-gray-800 px-5 py-3 rounded-sm flex items-start gap-2">
                       
                        @if($button['icon'])
                       <flux:icon icon="{{ $button['icon'] }}" class="w-6"></flux:icon>
                       @else
                       <flux:icon icon="stop" class="size-8"></flux:icon>
                       @endif

                          
                       <div class="text-sm text-left"> {{ $button['name'] }}</div>
                    </button>
                @endforeach
            </div>
            

            <h2 class="text-sm text-gray-500 font-bold mb-2">{{__('Your sections')}}</h2>

            <div class="space-y-4">
                <ul class="space-y-1.5 flex flex-col">
                    @foreach ($sections as $index => $section)
                    <li 
                        x-data="{ 
                            isOpen: false,
                            order: $store.sectionOrders.get('{{ $section['id'] }}') || {{ $index }}
                        }"
                        :style="{ '--section-order': $store.sectionOrders.get('{{ $section['id'] }}') || {{ $index }} }"
                        class="section-item cursor-pointer bg-white p-3 rounded-sm shadow-sm border border-gray-100 hover:border-blue-500">
                        <div @click="isOpen = !isOpen" class="flex gap-4 items-center justify-between">
                        <div class="flex gap-2 items-center">
                            <div class="flex flex-col">
                                <button @click.stop="
                                    const currentOrder = $store.sectionOrders.get('{{ $section['id'] }}');
                                    $store.sectionOrders.set('{{ $section['id'] }}', currentOrder - 1);
                                " class="text-zinc-500 hover:text-blue-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                    </svg>
                                </button>
                                <button @click.stop="
                                    const currentOrder = $store.sectionOrders.get('{{ $section['id'] }}');
                                    $store.sectionOrders.set('{{ $section['id'] }}', currentOrder + 1);
                                " class="text-zinc-500 hover:text-blue-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </div>

                            <input class="font-bold text-sm select-none" wire:model.live="sections.{{ $index }}.name"/>
                        </div>
                            <div class="text-zinc-500 transition-transform duration-200" :class="isOpen ? 'rotate-180' : 'rotate-0'">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>

                            </div>

                        </div>
                        <div 
                            x-show="isOpen"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="transform opacity-0 -translate-y-2"
                            x-transition:enter-end="transform opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="transform opacity-100 translate-y-0"
                            x-transition:leave-end="transform opacity-0 -translate-y-2"
                            class="mt-2 space-y-2"
                        >
  <div wire:ignore.self>

                        @foreach($this->getEditorButtons()[$section['type']]['schema'] ?? [] as $name => $schema)


<div 

                :key="'field-' . $section['id'] . '-' . $name"
                wire:ignore
                class="my-4">


            <livewire:fields
                :schema="$schema"
                :index="$index"
                :name="$name"
                :content="$section['content']"
                :key="'field-' . $section['id'] . '-' . $name"
                
                />


</div>

        @endforeach



                            <div class="flex justify-between my-3">
                                <div class="flex gap-2">
                                    <flux:button wire:click="duplicateSection({{ $index }})"  size="xs">{{__('Duplicate')}}</flux:button>
                                    <flux:button wire:confirm="{{__('Are you sure you want to delete this section?')}}" wire:click="removeSection({{ $index }})" variant="danger" size="xs">{{__('Delete section')}}</flux:button>
                                </div>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
            <flux:spacer></flux:spacer>
            <div class="grow">

        </div>
        </div>

        <!-- Preview Column (1/2) -->
        <div class="w-3/4 p-4 bg-white overflow-y-auto h-screen">





<div class="flex justify-between mb-2">
    <h2 class="text-xl font-bold mb-4">Forhåndsvisning</h2>

            <flux:button.group>
    <flux:button @click="previewMode = 'desktop'">PC</flux:button>
    <flux:button @click="previewMode = 'mobile'">Mobil</flux:button>
</flux:button.group>
</div>
            <!-- Browser Frame -->
            <div class="w-full rounded-md border shadow-lg h-[90vh] overflow-y-scroll @container" :class="previewMode === 'mobile' ? 'max-w-[390px] mx-auto' : 'w-full'">
                <!-- Browser Title Bar -->
                <div class="bg-gray-100 p-2 flex items-center justify-between border-b">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                        <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    </div>
                </div>
            
                <!-- Preview Area -->
                <div class="overflow-x-hidden overflow-y-scroll">
                    @foreach ($sections as $section)
                    <div wire:key="preview-{{ $section['id'] }}">
                        @livewire("{$section['namespace']}.{$section['type']}", [
                            'content' => $section['content'], 
                            'index' => $loop->index
                        ], key("preview-child-{$section['id']}-" . now()->timestamp))
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>