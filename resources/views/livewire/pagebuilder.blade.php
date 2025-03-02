<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Page;
use Illuminate\Support\Facades\File;

new #[Layout('components.layouts.app.blank')] class extends Component
{
    public $page;
    public $sections = [];
    public $listeners = ['updateSectionContent'];

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
        $this->sections[$index]['content'] = array_merge(
            $this->sections[$index]['content'] ?? [],
            $content
        );
        
        $this->savePage();
    }

    public function savePage()
    {
        $this->page->sections = $this->sections;
        $this->page->save();
    }

    public function moveSection($oldIndex, $newIndex)
    {
        if ($oldIndex !== $newIndex) {
            $section = $this->sections[$oldIndex];
            array_splice($this->sections, $oldIndex, 1);
            array_splice($this->sections, $newIndex, 0, [$section]);
        }
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





<script src="
https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js
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
                    <button wire:click="addSection('{{ $type }}', '{{ $button['name'] }}')" class="bg-white border border-gray-300 hover:border-blue-500 text-gray-800 px-5 py-3 rounded-sm flex items-start gap-2">
                       
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

            <div class="space-y-4" x-data="{
                initSortable() {
                    let el = document.getElementById('sections-list');
                    Sortable.create(el, {
                        handle: '.drag-handle',
                        onEnd: (evt) => {
                            @this.call('moveSection', evt.oldIndex, evt.newIndex);
                        }
                    });
                }
            }" x-init="initSortable">
                <ul id="sections-list" class="space-y-1.5">
                    @foreach ($sections as $index => $section)
                    <li wire:key="editor-{{ $section['id'] }}"  class="drag-handle cursor-move cursor-pointer bg-white p-3 rounded-sm shadow-sm border border-gray-100 hover:border-blue-500" x-data="{ isOpen: false }">
                        <div @click="isOpen = !isOpen" class="flex gap-4 items-center">
                            <div class=" text-gray-500">&#x2630;</div>
                            <h3 class="font-bold text-sm  select-none">{{ $section['name'] ?? $section['type'] }}</h3>
                        </div>
                        <div x-show="isOpen" class="mt-2 space-y-2">

                        @foreach($this->getEditorButtons()[$section['type']]['schema'] ?? [] as $name => $schema)

                    
                         <livewire:fields
                            :schema="$schema"
                            :index="$index"
                            :name="$name"
                            :content="$section['content']"

                    
                           wire:key="editor-{{ $section['id'] }}-{{ $index }} . {{ rand() }}"
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
            <div class="w-full rounded-md border shadow-lg  h-[80vh]" :class="previewMode === 'mobile' ? 'max-w-xs mx-auto' : 'w-full'">


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
                    <div class="" wire:key="preview-{{ $loop->index }}">

                        @livewire("{$section['namespace']}.{$section['type']}", [
                            'content' => $section['content'], 
                            'index' => $loop->index
                        ], key("preview-child-{$section['id']}-".now()->timestamp))
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
