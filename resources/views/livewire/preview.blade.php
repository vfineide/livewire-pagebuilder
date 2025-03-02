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

    @foreach ($sections as $section)
    <div class="" wire:key="preview-{{ $loop->index }}">

        @livewire("{$section['namespace']}.{$section['type']}", [
            'content' => $section['content'], 
            'index' => $loop->index
        ], key("preview-child-{$section['id']}-".now()->timestamp))
    </div>
    @endforeach
</div>
