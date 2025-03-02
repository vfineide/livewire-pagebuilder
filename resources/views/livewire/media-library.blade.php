<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;


new class extends Component
{
    use WithFileUploads;


    #[Validate('image|max:10024')] // 1MB Max
    public $photo;
    public $library;
    public $selectedMediaIds = [];
    public $selectedMedia;
    public $mediaTab;

    public function save()
    {
        try {
            $this->validate();
            
            // Generate a hashed filename with original extension
            $extension = $this->photo->getClientOriginalExtension();
            $hashedName = md5($this->photo->getClientOriginalName() . time()) . '.' . $extension;
            

            //Storage::disk('r2')->put('media/'.$hashedName, $this->photo);

            $this->photo->storeAs('media', $hashedName);



            \Log::info('File stored at: ' . $path);

            $media = new Media();
            $media->path = $path;
            $media->name = $this->photo->getClientOriginalName();
            $media->mime_type = $this->photo->getMimeType();
            $media->disk = 'r2';  // Set disk to 'r2'
            $media->size = $this->photo->getSize();
            $media->save();
            
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
    }

    public function unselectMedia()
    {
        $this->selectedMediaIds = [];
        $this->selectedMedia = null;
    }

};
?>
<div class="max-w-[320px]">
   
    <div class="media-library">

    
        <!-- Main Media Library Content -->
        <div class="media-library-content">
            <!-- The Blade portion, using FluxUI tabs -->
            @if ($selectedMediaIds)
            <div class="relative rounded">
                <button 
                    wire:click="unselectMedia" 
                    class="absolute top-2 right-2 w-6 h-6 flex items-center justify-center bg-black bg-opacity-50 hover:bg-opacity-70 rounded-full text-white transition-all"
                >
                    ×
                </button>
                <img src="{{ Storage::disk()->url($selectedMedia->path) }}" alt="Selected Media" class="w-full h-auto rounded"/>
            </div>
            @else
            <flux:tab.group class="media-library-tabs">
                <flux:tabs variant="segmented" wire:model="mediaTab">
                    <flux:tab name="upload">Last opp</flux:tab>
                    <flux:tab name="browse" wire:click="loadMedia">Bla i bilder</flux:tab>
                </flux:tabs>

                <!-- ====== UPLOAD TAB PANEL ====== -->
                <flux:tab.panel name="upload" class="pt-0">

        

<form wire:submit="save">
    @if ($photo) 
        <img src="{{ $photo->temporaryUrl() }}">
    @endif
 
    <flux:input type="file" wire:model="photo"/>
 
    @error('photo') <span class="error">{{ $message }}</span> @enderror
 
    <flux:button type="submit" variant="primary">Save photo</flux:button>
</form>


                </flux:tab.panel>

                <!-- ====== BROWSE TAB PANEL ====== -->
                <flux:tab.panel name="browse" class="pt-0">
                    <div class="grid grid-cols-4 gap-4">
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
                                    style="break-inside: avoid;"
                                >
                                    @if(in_array($media['id'], $selectedMediaIds))
                                        <button class="unselect-button" wire:click.stop="selectMedia({{ $media['id'] }})">
                                            ✕
                                        </button>
                                    @endif


                                    {{ Storage::get($media['path']) }}

                                    @if(Str::startsWith($media['mime_type'], 'image/'))
                                        <img
                                            src="{{ Storage::disk()->url($media['path']) }}"
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
                        </div>
                    @else
                        <div class="text-center text-gray-500">No media found</div>
                    @endif
                    </div>
                </flux:tab.panel>
            </flux:tab.group>
            @endif
        </div>
    </div>
</div>