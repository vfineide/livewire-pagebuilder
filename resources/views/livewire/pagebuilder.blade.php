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
    public $listeners = ['updateSectionContent', 'selectedMedia'];

    public function mount($id)
    {
        $this->page = Page::findOrFail($id);
        $this->sections = $this->page->sections ?? [];
    }


    public function addSection($type, $name)
    {
        $buttons = $this->getEditorButtons();
        if (!isset($buttons[$type])) {
            throw new \Exception("Unknown section type: {$type}");
        }
 
        $this->sections[] = [
            'id' => uniqid(),
            'name' => $name,
            'type' => $type,
            'content' => [],
            'namespace' => $buttons[$type]['namespace']
        ];
       
    }

    public function removeSection($index)
    {
        unset($this->sections[$index]);
        $this->sections = array_values($this->sections);
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

        $this->sections[$index]['content'] = array_merge(
            $this->sections[$index]['content'] ?? [],
            $processedContent
        );
        
        $this->savePage();
    }

    public function savePage()
    {
        $this->page->sections = $this->sections;
        $this->page->save();
    }

// In your PageBuilder component
public function moveSection(array $items)
{
    // $items is an array like:
    // [ ['value' => 'abc123', 'order' => 1], ['value' => 'def456', 'order' => 2], … ]

    // Build a map: sectionId → newOrder
    $orderMap = collect($items)->pluck('order', 'value');

    // Reorder $this->sections by that map
    $this->sections = collect($this->sections)
        ->sortBy(fn($section) => $orderMap->get($section['id']))
        ->values()
        ->toArray();

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
}">



<script defer src="https://cdn.jsdelivr.net/gh/livewire/sortable@v1.x.x/dist/livewire-sortable.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/filepond/4.32.6/filepond.js" integrity="sha512-9NomenG8ZkuctRQaDSN74Y0kyM2+1FGJTunuSfTFqif+vRrDZM2Ct0Ynp3CIbMNUQOWxd5RCyXexZzlz7KvUcw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/filepond/4.32.6/filepond.css" integrity="sha512-lBRULj1QwuG+xVoiKYx4nmB+CqtaTUs3J21JnY/GiDdYMRfX1i2pcRGbhkGF7hCZJz5e+rV4jjlmSYFIwZARsQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<script src="
https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-type@1.2.9/dist/filepond-plugin-file-validate-type.min.js
"></script>


<style>
    [data-flux-main] {
        padding: 0 !important;
    }

    #flux-sidebar {
        display: none;
    }
</style>
    <div class="flex">
        <!-- Editor Column (1/2) -->
        <div class="w-1/4 p-4 bg-gray-100 overflow-y-scroll h-screen space-y-4 flex flex-col">

            <div>
            <flux:button variant="ghost" inset="left" href="/admin/pages">Tilbake</flux:button>
            </div>
            <h2 class="text-xl font-bold mb-4">Sidebygger</h2>


            <h2 class="text-sm text-gray-500 font-bold mb-2">Legg til seksjoner</h2>

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
            

            <h2 class="text-sm text-gray-500 font-bold mb-2">Dine seksjoner</h2>

            <div class="space-y-4">
                <ul id="sections-list" wire:sortable="moveSection" class="space-y-1.5">
                    @foreach ($sections as $index => $section)
                    <li 
                    
      wire:sortable.item="{{ $section['id'] }}" 
      wire:key="editor-{{ $section['id'] }}"
                    
                    class="cursor-move cursor-pointer bg-white p-3 rounded-sm shadow-sm border border-gray-100 hover:border-blue-500" x-data="{ isOpen: false }">
                        <div @click="isOpen = !isOpen" class="flex gap-4 items-center">
                                 <span wire:sortable.handle class=" drag-handle  cursor-move mr-2">☰</span>

                            <h3 class="font-bold text-sm  select-none">{{ $section['name'] ?? $section['type'] }}</h3>
                        </div>
                        <div x-show="isOpen" class="mt-2 space-y-2">

                        @foreach($this->getEditorButtons()[$section['type']]['schema'] ?? [] as $name => $schema)
                    
                         <livewire:fields
                            :schema="$schema"
                            :index="$index"
                            :name="$name"
                            :content="$section['content']"
                            :key="'fields-' . $section['id'] . '-' . $name"

                    
                        />

                        @endforeach



                            <div class="flex justify-between mt-2">
                                <flux:button wire:click="removeSection({{ $index }})" variant="danger" size="sm">Slett</flux:button>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
            <flux:spacer></flux:spacer>
            <div class="grow">
            <flux:button wire:click="savePage" class="w-full mt-1">Lagre rekkefølge</flux:button>


            

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
            <div class="w-full rounded-md border shadow-lg  h-[90vh] overflow-y-scroll" :class="previewMode === 'mobile' ? 'max-w-xs mx-auto' : 'w-full'">


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
                    <div class="" wire:key="preview-{{ $section['id'] }}">


                        @livewire("{$section['namespace']}.{$section['type']}", [
                            'content' => $section['content'], 
                            'index' => $loop->index
                        ], key("preview-child-{$section['id']}-"))
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>
