<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;


new class extends Component
{
    use WithFileUploads;

    #[Validate('image|max:20024')] // 1MB Max
    public $photo;
    public $photoPreview = null;
    public $library;
    public $selectedMediaIds = [];
    public $selectedMedia;
    public $mediaTab;

    public function mount()
    {
        $this->library = collect(); // Initialize as empty collection
    }

    // Automatically trigger save when a file is selected
    public function updatedPhoto()
    {
        if ($this->photo) {
            $this->photoPreview = $this->photo->temporaryUrl();
            // After successful upload, we'll want to select the new media
            $this->save();
        }
    }

    public function save()
    {
        try {
            $this->validate();
            
            // Generate a hashed filename with original extension
            $extension = $this->photo->getClientOriginalExtension();
            $hashedName = md5($this->photo->getClientOriginalName() . time()) . '.' . $extension;
            
            $this->photo->storeAs('media', $hashedName, ['visibility' => 'public']);

            $path = 'media/'.$hashedName;

            $media = new Media();
            $media->path = $path;
            $media->name = $this->photo->getClientOriginalName();
            $media->mime_type = $this->photo->getMimeType();
            $media->disk = 'r2';
            $media->size = $this->photo->getSize();
            $media->save();

            // Load the library before selecting the new media
            $this->loadMedia();
            // Select the newly uploaded media
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
    }

    public function unselectMedia()
    {
        $this->selectedMediaIds = [];
        $this->selectedMedia = null;
        $this->photoPreview = null;
        $this->photo = null;
    }

};
?>
<div class="max-w-[320px]">
    <div class="media-library">
        <!-- Selected Media or Preview Display -->
        @if($selectedMedia || $photoPreview)
            <div class="relative rounded mb-4">
                <button 
                    wire:click="unselectMedia" 
                    class="absolute top-2 right-2 w-6 h-6 flex items-center justify-center bg-black bg-opacity-50 hover:bg-opacity-70 rounded-full text-white transition-all"
                >
                    ×
                </button>
                @if($selectedMedia)
                    <img src="{{ Storage::disk()->url($selectedMedia->path) }}" alt="Selected Media" class="w-full h-auto rounded"/>
                @elseif($photoPreview)
                    <div class="relative">
                        <img src="{{ $photoPreview }}" alt="Preview" class="w-full h-auto rounded"/>
                        <div 
                            wire:loading 
                            wire:target="photo"
                            class="absolute inset-0 bg-black bg-opacity-50 rounded flex items-center justify-center"
                        >
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Main Media Library Content -->
        @if(!$selectedMedia && !$photoPreview)
            <div class="media-library-content">
                <flux:tab.group class="media-library-tabs">
                    <flux:tabs variant="segmented" wire:model="mediaTab">
                        <flux:tab name="upload">Last opp</flux:tab>
                        <flux:tab name="browse" wire:click="loadMedia">Bla i bilder</flux:tab>
                    </flux:tabs>

                    <!-- ====== UPLOAD TAB PANEL ====== -->
                    <flux:tab.panel name="upload" class="pt-0">
                        <div>
                            <!-- Remove the old loader -->
                            <flux:input id="photoInput" type="file" wire:model="photo"/>
                            @error('photo') <span class="error">{{ $message }}</span> @enderror
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
                                        style="break-inside: avoid;"
                                    >
                                        @if(in_array($media['id'], $selectedMediaIds))
                                            <button class="unselect-button" wire:click.stop="selectMedia({{ $media['id'] }})">
                                                ✕
                                            </button>
                                        @endif

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
                            @else
                                <div class="text-center text-gray-500">Ingen bilder funnet</div>
                            @endif
                        </div>
                    </flux:tab.panel>
                </flux:tab.group>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('photoInput');
    // Remove the loader related code since we're using wire:loading now
});
</script>