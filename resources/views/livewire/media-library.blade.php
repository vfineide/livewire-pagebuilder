<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


new class extends Component
{
    use WithFileUploads;

    #[Validate('image|max:80024')] // 8MB Max
    public $photo;
    public $library;
    public $selectedMediaIds = [];
    public $selectedMedia;
    public $mediaTab;
    public $fieldName;
    public $fieldLabel;
    public $section;

    public $editedMedia;
    public $editedMediaAlt;
    public $editedMediaCaption;
    public $editedMediaName;
    public $id;

public function mount($fieldName, $fieldLabel, $section, $multiple = false)
{
    $this->library = collect();
    $this->fieldName = $fieldName;
    $this->fieldLabel = $fieldLabel;
    $this->section = $section;

    $this->id = $section[$this->fieldName . '.meta']['id'];


    // If we have content for this field, show it as selected
    if (isset($section[$this->fieldName])) {
        $mediaData = $section[$this->fieldName];
        if (is_array($mediaData) && isset($mediaData['id'])) {
            $media = Media::find($mediaData['id']);
            if ($media) {
                $this->selectedMedia = $media;
                $this->selectedMediaIds = [$media->id];
            }
        }
    }
}

    // Automatically trigger save when a file is selected
    public function updatedPhoto()
    {
        if ($this->photo) {
            $this->save();
        }
    }

    public function save()
    {
        try {
            $this->validate();
            
            $extension = $this->photo->getClientOriginalExtension();
            $hashedName = md5($this->photo->getClientOriginalName() . time()) . '.' . $extension;
            
            // Determine disk based on environment
            $disk = app()->environment('production') ? 'public' : 'public';
            
            $this->photo->storeAs('media', $hashedName, ['disk' => $disk]);
            $path = 'media/'.$hashedName;

            $media = new Media();
            $media->path = $path;
            $media->name = $this->photo->getClientOriginalName();
            $media->mime_type = $this->photo->getMimeType();
            $media->disk = $disk; // Save which disk was used
            $media->size = $this->photo->getSize();
            $media->save();

            $this->loadMedia();
            $this->selectMedia($media->id);
            
        } catch (\Exception $e) {
            \Log::error('Upload failed: ' . $e->getMessage());
            throw $e;
        }
    }


    public function loadMedia()
    {
        $this->library = Media::latest()->get();
    }

    public function selectMedia($id)
    {
        $this->selectedMedia = $this->library->firstWhere('id', $id);

        if (in_array($id, $this->selectedMediaIds)) {
            $this->selectedMediaIds = array_diff($this->selectedMediaIds, [$id]);
        } else {
            $this->selectedMediaIds[] = $id;
        }

        // Dispatch with section and repeater IDs
        $this->dispatch('media-selected', [
            'media' => [
                'id' => $this->selectedMedia->id,
                'path' => $this->selectedMedia->path,
                'name' => $this->selectedMedia->name
            ],
            'id' => $this->id
        ]);

    }

    public function unselectMedia()
    {
        $this->selectedMediaIds = [];
        $this->selectedMedia = null;
        $this->photo = null;
        
        $this->dispatch('media-selected', [
            'media' => [],
            'id' => $this->id
        ]);
    }

    public function editMedia()
    {
      $this->editedMediaName = $this->selectedMedia->name;
      Flux::modal('media-single-modal-' . $this->id)->show();
    }
    

    public function editMediaSave()
    {
        $this->selectedMedia->name = $this->editedMediaName;
        $this->selectedMedia->save();
        Flux::modal('media-single-modal-' . $this->id)->close();
    }

};
?>
<div class="max-w-[320px]">

               <flux:label>{{ $fieldLabel ?? 'Bilde' }}</flux:label>

    <div class="media-library">
        <!-- Selected Media or Preview Display -->
        @if($selectedMedia)
    <div class="relative rounded mb-4 group">
                <button 
                    wire:click="editMedia" 
                    class="cursor-pointer absolute top-2 right-9 w-6 h-6 flex items-center justify-center bg-black/60 rounded-full text-white hover:bg-black/80 transition-all duration-200"
                >
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
  <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
</svg>

                </button>

                <button 
                    wire:click="unselectMedia" 
                    class="cursor-pointer absolute top-2 right-2 w-6 h-6 flex items-center justify-center bg-black/60 rounded-full text-white hover:bg-black/80 transition-all duration-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>



                
                    <img src="{{ Storage::url($selectedMedia->path) }}" alt="Selected Media" class="w-full h-auto rounded"/>
            
        
            </div>
        @endif

        <!-- Main Media Library Content -->
        @if(!$selectedMedia) 
            <div class="media-library-content">
                <flux:tab.group class="media-library-tabs">
                    <flux:tabs variant="segmented" wire:model="mediaTab">
                        <flux:tab name="upload">Last opp</flux:tab>
                        <flux:tab name="browse" wire:click="loadMedia">Bla i bilder</flux:tab>
                    </flux:tabs>

                    <!-- ====== UPLOAD TAB PANEL ====== -->
                    <flux:tab.panel name="upload" class="pt-0">
                        <div x-data="{ 
                            init() {
                                const input = this.$refs.input;
                                const pond = FilePond.create(input, {
                                    server: {
                                        process: (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                                            this.$wire.upload('photo', file, load, error, progress)
                                        }
                                    },
                                    credits: false,
                                    allowMultiple: false,
                                    acceptedFileTypes: ['image/*']
                                });

                                // Cleanup on component disconnect
                                this.$cleanup = () => {
                                    pond.destroy();
                                };
                            }
                        }">
                            <input type="file" x-ref="input" class="filepond">
                        </div>
                    </flux:tab.panel>

                    <!-- ====== BROWSE TAB PANEL ====== -->
                    <flux:tab.panel name="browse" class="pt-0">
                        <div class="grid grid-cols-3 gap-2">
                            @if ($library)
                                @foreach($library as $media)
                                    <div
                                        class="
                                            media-item
                                            mb-2 
                                            cursor-pointer hover:bg-gray-100 
                                            border border-white rounded
                                            @if(in_array($media['id'], $selectedMediaIds)) selected shadow outline outline-2 outline-black @endif
                                            break-inside-avoid
                                        "
                                        wire:click="selectMedia({{ $media['id'] }})"
                                        wire:key="media-{{ $media->id }}"
                                        style="break-inside: avoid;"
                                    >
                                        @if(in_array($media['id'], $selectedMediaIds))
                                            <button class="unselect-button" wire:click.stop="selectMedia({{ $media['id'] }})">
                                                ✕
                                            </button>
                                        @endif

                                        @if(Str::startsWith($media['mime_type'], 'image/'))
                                            <img
                                                src="{{ Storage::url($media['path']) }}"
                                                alt="{{ $media['name'] }}"
                                                class="block w-full h-auto rounded"
                                            />
                                        @else
                                            <div class="text-center text-xs text-gray-600 p-2">
                                                {{ $media['name'] }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center text-gray-500">Ingen bilder funnet</div>
                            @endif
                        </div>
                    </flux:tab.panel>
                </flux:tab.group>
            </div>
        @endif
    </div>



<flux:modal name="media-single-modal-{{ $id }}" class="w-3xl">


<flux:heading>Bildeinnstillinger</flux:heading>
@if($selectedMedia)

<img src="{{ Storage::url($selectedMedia->path) }}" alt="Selected Media" class="w-full h-auto rounded"/>

<flux:input wire:model="editedMediaName" label="Tittel" />
<flux:input wire:model="editedMediaAlt" label="Alt" />
<flux:input wire:model="editedMediaCaption" label="Beskrivelse" />

<flux:button wire:click="editMediaSave">Lagre</flux:button>
@endif

</flux:modal>
</div>