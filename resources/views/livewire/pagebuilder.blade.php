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
    public $rawContent = '';
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
        
        $this->rawContent = json_encode($this->sections, JSON_PRETTY_PRINT);
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
        // Use a single database transaction for better performance
        \DB::transaction(function () {
            $this->page->sections = $this->sections;
            $this->page->save();
        });
    }

    public function saveRawContent()
    {
        try {
            $decodedContent = json_decode($this->rawContent, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->page->sections = $decodedContent;
                $this->page->save();
                $this->sections = $decodedContent;
            }
        } catch (\Exception $e) {
            // Handle error if needed
        }
    }

    public function moveSection($fromIndex, $toIndex)
    {
        // Validate indices
        if ($fromIndex < 0 || $fromIndex >= count($this->sections) ||
            $toIndex < 0 || $toIndex >= count($this->sections)) {
            return;
        }

        // Get the section to move
        $section = $this->sections[$fromIndex];
        
        // Remove the section from its current position
        array_splice($this->sections, $fromIndex, 1);
        
        // Insert the section at the new position
        array_splice($this->sections, $toIndex, 0, [$section]);
        
        // Update the sections array to ensure proper indexing
        $this->sections = array_values($this->sections);
        
        // Save the changes using a transaction
        \DB::transaction(function () {
            $this->page->sections = $this->sections;
            $this->page->save();
        });

        // Force a full page refresh
        $this->redirect(request()->header('Referer'));
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

    public function blockExists($type)
    {
        $path = resource_path('views/livewire/blocks/' . $type . '.blade.php');
        return File::exists($path);
    }

    // Add a new method for batch updates
    public function batchUpdateSections($updates)
    {
        foreach ($updates as $update) {
            $sectionIndex = $update['sectionIndex'];
            $fieldId = $update['fieldId'];
            $fieldName = $update['fieldName'];
            $content = $update['content'];

            if (isset($this->sections[$sectionIndex])) {
                $this->sections[$sectionIndex]['content'] = array_merge(
                    $this->sections[$sectionIndex]['content'] ?? [],
                    $content
                );
            }
        }

        // Save all changes at once
        $this->savePage();
    }
}; ?>


<div  x-data="{ 
    sections: @entangle('sections'), 
    previewMode: 'desktop',
    openSectionId: null,
    pendingUpdates: [],
    updateTimeout: null,
    toggleSection(id) {
        this.openSectionId = this.openSectionId === id ? null : id;
    },
    queueUpdate(update) {
        this.pendingUpdates.push(update);
        clearTimeout(this.updateTimeout);
        this.updateTimeout = setTimeout(() => {
            if (this.pendingUpdates.length > 0) {
                $wire.batchUpdateSections(this.pendingUpdates);
                this.pendingUpdates = [];
            }
        }, 500);
    }
}" x-init="
    $store.sectionOrders = new Map();
    sections.forEach((section, index) => {
        $store.sectionOrders.set(section.id, index);
    });
" @toggle-section.window="toggleSection($event.detail.id)"
   @sections-reordered.window="$nextTick(() => {
        $store.sectionOrders = new Map();
        sections.forEach((section, index) => {
            $store.sectionOrders.set(section.id, index);
        });
    })">


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

            <div class="flex gap-2 mb-4">
                <flux:modal.trigger name="raw-content">
                    <flux:button variant="outline" size="sm">Raw</flux:button>
                </flux:modal.trigger>
            </div>

            <h2 class="text-sm text-gray-500 font-bold mb-2">{{__('Available sections')}}</h2>

            <div class="mb-4 grid grid-cols-2 gap-1" >
                @foreach($this->getEditorButtons() as $type => $button)
                    <button wire:key="add-section-{{ $type }}" wire:click="addSection('{{ $type }}', '{{ $button['name'] }}')" class="cursor-pointer bg-white border border-gray-300 hover:border-blue-500 text-gray-800 px-5 py-3 rounded-xs flex items-start gap-2">
                       

                       {{-- 
                        @if($button['icon'])
                       <flux:icon icon="{{ $button['icon'] }}" class="w-6"></flux:icon>
                       @else
                       <flux:icon icon="stop" class="size-8"></flux:icon>
                       @endif

                       --}}

                          
                       <div class="text-sm text-left"> {{ $button['name'] }}</div>
                    </button>
                @endforeach
            </div>
            

            <h2 class="text-sm text-gray-500 font-bold mb-2">{{__('Your sections')}}</h2>

            <div class="space-y-4">
                <ul class="space-y-1 flex flex-col">
                    @foreach ($sections as $index => $section)
                    <li 
                    wire:key="section-{{ $section['id'] }}"
                        class="section-item cursor-pointer bg-white px-3 py-2  rounded-sm border border-gray-200 hover:border-blue-500">
                        <div @click="toggleSection('{{ $section['id'] }}')" class="min-h-8 flex gap-4 items-center justify-between">
                        <div class="flex gap-2 items-center">
                            <div class="flex flex-col -ml-1.5">
                                <button @click.stop="$wire.moveSection({{ $index }}, {{ $index - 1 }})" 
                                    :class="{ 'opacity-50 cursor-not-allowed hidden': {{ $index }} === 0 }"
                                    :disabled="{{ $index }} === 0"
                                    class="cursor-pointer text-zinc-300 px-0.5 rounded hover:text-black hover:bg-zinc-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                    </svg>
                                </button>
                                <button @click.stop="$wire.moveSection({{ $index }}, {{ $index + 1 }})"
                                    :class="{ 'opacity-50 cursor-not-allowed hidden': {{ $index }} === sections.length - 1 }"
                                    :disabled="{{ $index }} === sections.length - 1"
                                    class="cursor-pointer text-zinc-300 px-0.5 rounded hover:text-black hover:bg-zinc-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </div>

<div class="font-bold text-sm">
    {{ $section['name'] }}
</div>
                        </div>
                            <div class="text-zinc-500 transition-transform duration-200" :class="openSectionId === '{{ $section['id'] }}' ? 'rotate-180' : 'rotate-0'">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                            </div>
                        </div>
                        <div 
                            x-show="openSectionId === '{{ $section['id'] }}'"
                            x-transition:enter="transition ease-out duration-100"
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

            <flux:modal name="raw-content" class="md:w-[800px]">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Raw Page Content</flux:heading>
                        <flux:text class="mt-2">Edit the raw page content in JSON format.</flux:text>
                    </div>

                    <div class="relative">
                        <textarea 
                            wire:model="rawContent"
                            class="w-full h-[500px] font-mono text-sm p-4 border rounded-md"
                        ></textarea>
                    </div>

                    <div class="flex">
                        <flux:spacer />
                        <flux:button 
                            wire:click="saveRawContent"
                            variant="primary"
                        >Save changes</flux:button>
                    </div>
                </div>
            </flux:modal>

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
                    @foreach ($sections as $index => $section)
                    <div wire:key="preview-{{ $section['id'] }}-{{ $index }}" class="group relative">
                        <div class="absolute top-4 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                            <flux:button 
                                type="button"
                                variant="filled"
                                class="cursor-pointer z-10"
                                @click="$dispatch('toggle-section', { id: '{{ $section['id'] }}' })"
                            >
                                Edit
                            </flux:button>
                        </div>
                        @if($this->blockExists($section['type']))
                            @livewire("{$section['namespace']}.{$section['type']}", [
                                'content' => $section['content'], 
                                'index' => $index
                            ], key("preview-child-{$section['id']}-{$index}-" . now()->timestamp))
                        @else
                            <div class="p-4 border border-red-200 bg-red-50 rounded-md m-4">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-sm font-medium text-red-800">Block Not Found</h3>
                                        <div class="mt-2 text-sm text-red-700">
                                            <p>The block type "{{ $section['type'] }}" could not be found. This block may have been deleted or moved.</p>
                                        </div>
                                        <div class="mt-4">
                                            <button type="button" x-data x-on:click="$el.nextElementSibling.classList.toggle('hidden')" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                View Schema
                                            </button>
                                            <pre class="mt-4 hidden bg-white p-4 rounded-md overflow-auto max-h-96 border border-gray-200 text-sm">{{ json_encode($section, JSON_PRETTY_PRINT) }}</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>