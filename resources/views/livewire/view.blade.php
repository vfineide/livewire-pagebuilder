<?php

use Livewire\Volt\Component;
use App\Models\Page;
use Illuminate\Support\Facades\File;

new Class extends Component
{
    public $page;
    public $sections = [];
    public $listeners = ['updateSectionContent'];

    public function mount($slug)
    {
        $this->page = Page::where('slug', $slug)->firstOrFail();
        $this->sections = $this->page->sections ?? [];
    }

    public function dehydrate()
    {
        // Exclude sections from serialization to reduce wire:snapshot size
        $this->sections = [];
    }

    public function hydrate()
    {
        // Reload page and sections from database on each request
        if ($this->page) {
            $this->page = $this->page->fresh();
            $this->sections = $this->page->sections ?? [];
        }
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
        
        // Save immediately to persist changes
        $this->savePage();
    }

    public function removeSection($index)
    {
        unset($this->sections[$index]);
        $this->sections = array_values($this->sections);
        
        // Save immediately to persist changes
        $this->savePage();
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
            
            // Save immediately to persist changes
            $this->savePage();
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
}" class="@container">

    @foreach ($sections as $section)
    <div class="" wire:key="preview-{{ $loop->index }}">

        @livewire("{$section['namespace']}.{$section['type']}", [
            'content' => $section['content'], 
            'index' => $loop->index
        ], key("preview-child-{$section['id']}-".now()->timestamp))
    </div>
    @endforeach
</div>
