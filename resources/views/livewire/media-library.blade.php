<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Media;
use Livewire\Attributes\On;

new class extends Component
{
    // Model property (e.g. 'image', 'avatar', etc.)
    public $model;

    // Tab state for FluxUI
    public $mediaTab = 'upload'; 

    // Media library state
    public $searchTerm = '';
    public $mediaItems = [];
    public $selectedMediaIds = [];

    // Fields for editing media name & collection
    public $mediaEditId;
    public $mediaEditName;
    public $mediaEditCollection;

    // Add a unique ID for this component instance
    public $instanceId;

    // Add new property for multiple selection mode
    public $allowMultiple = false;

    // Add this property to the Component class
    public $hasSelection = false;

    // Listener for when Vapor finishes uploading
    protected $listeners = [
        'vaporFileUploaded' => 'handleVaporFileUploaded'
    ];

    /**
     * Set defaults & load media on mount.
     */
    public function mount($model = 'image', $multiple = false, $value = null)
    {
        \Log::info('Media Library Mount - Value received:', ['value' => $value]);
        
        $this->model = $model;
        $this->allowMultiple = $multiple;
        $this->instanceId = uniqid('media_');
        
        // If we have a value passed, set it as selected
        if ($value) {
            $this->selectedMediaIds = is_array($value) ? $value : [$value];
            $this->hasSelection = true;
            
            \Log::info('Setting selectedMediaIds:', ['ids' => $this->selectedMediaIds]);
            
            // Immediately load the media to ensure the preview shows
            $this->loadMedia();
        }
    }

    /**
     * Reload the media library whenever searchTerm changes.
     */
    public function updatedSearchTerm()
    {
        $this->loadMedia();
    }

    /**
     * Fetch media from DB for the current user, filtered by search.
     */
    public function loadMedia()
    {
        \Log::info('LoadMedia called with selectedMediaIds:', ['ids' => $this->selectedMediaIds]);
        
        $userId = Auth::user()->id ?? null;

        // First, if we have selected media IDs, make sure we load those specific items
        $selectedMedia = collect([]);
        if (!empty($this->selectedMediaIds)) {
            $query = Media::whereIn('id', $this->selectedMediaIds);
                
            \Log::info('Selected media query:', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);
            
            $selectedMedia = $query->get();
            
            \Log::info('Selected media results:', ['count' => $selectedMedia->count(), 'items' => $selectedMedia->toArray()]);
        }

        // Then load the regular filtered results
        $filteredMedia = Media::when($this->searchTerm, function ($q) {
                $q->where('name', 'like', '%'.$this->searchTerm.'%');
            })
            ->whereNotIn('id', $this->selectedMediaIds)
            ->latest()
            ->take(12)
            ->get();

        // Merge both collections and convert to array
        $this->mediaItems = $selectedMedia->concat($filteredMedia)->all();
        
        \Log::info('Final mediaItems count:', ['count' => count($this->mediaItems)]);
    }

    /**
     * Select/unselect a media item. If clicking the same item again, unselect it.
     * Also load up the edit fields if selected.
     */
    public function selectMedia($mediaId)
    {
        if ($this->allowMultiple) {
            // For multiple mode: toggle selection in array
            if (in_array($mediaId, $this->selectedMediaIds)) {
                $this->selectedMediaIds = array_diff($this->selectedMediaIds, [$mediaId]);
            } else {
                $this->selectedMediaIds[] = $mediaId;
            }
            
            // Load edit fields for last selected item
            $this->loadEditFields(end($this->selectedMediaIds));
        } else {
            // Single-select behavior
            if ($this->selectedMediaIds === [$mediaId]) {
                $this->selectedMediaIds = [];
                $this->clearEditFields();
            } else {
                $this->selectedMediaIds = [$mediaId];
                $this->loadEditFields($mediaId);
            }
        }

        // Update hasSelection state
        $this->hasSelection = !empty($this->selectedMediaIds);

        // Notify parent with array of selected IDs
        $this->dispatch('mediaSelected', model: $this->model, mediaIds: $this->selectedMediaIds);
    }

    /**
     * Helper to load edit fields
     */
    private function loadEditFields($mediaId)
    {
        if ($mediaId) {
            $media = Media::find($mediaId);
            if ($media) {
                $this->mediaEditId = $media->id;
                $this->mediaEditName = $media->name;
                $this->mediaEditCollection = $media->collection ?? '';
            }
        }
    }

    /**
     * Helper to clear edit fields
     */
    private function clearEditFields()
    {
        $this->mediaEditId = null;
        $this->mediaEditName = null;
        $this->mediaEditCollection = null;
    }

    /**
     * Save name/collection changes to the selected media record.
     */
    public function updateMedia()
    {
        if ($this->mediaEditId) {
            $media = Media::find($this->mediaEditId);
            if ($media) {
                $media->update([
                    'name'       => $this->mediaEditName,
                    'collection' => $this->mediaEditCollection,
                ]);
            }
        }

        // Reload library to reflect updates
        $this->loadMedia();
    }

    /**
     * Called by the JS after Vapor finishes uploading to S3.
     */
    public function handleVaporFileUploaded($file, $instanceId)
    {


        // Only process if this is the target instance
        if ($instanceId !== $this->instanceId) {
            return;
        }

        $userId     = Auth::user()->id ?? 0;
        $sourcePath = $file['key'];            // e.g. "tmp/9a5b2a1e-..."
        $ext        = $file['extension'];      // e.g. "jpg"
        $finalPath  = 'images/' . basename($sourcePath) . '.' . $ext;

        // Copy from "tmp/..." to "images/..." on S3
        Storage::disk('s3')->copy($sourcePath, $finalPath);


        $storage = Storage::disk('s3');

        $thumbnailUrl = $storage->url($finalPath) . '?w=300&h=300&fit=crop';


        // Build a display name
        $displayName = $file['uuid'].'.'.$ext;

        // Extract mimeType from "headers" => ["Content-Type" => "image/jpeg"]
        $mimeType = 'application/octet-stream';
        if (isset($file['headers']['Content-Type'])) {
            $contentHeader = $file['headers']['Content-Type'];
            $mimeType      = is_array($contentHeader) ? $contentHeader[0] : $contentHeader;
        }

        // Create the Media record
        $media = Media::create([
            'user_id'           => $userId,
            'name'              => $displayName,         // e.g. "9a5b2a1e.jpg"
            'file_name'         => basename($finalPath), // e.g. "9a5b2a1e.jpg"
            'mime_type'         => $mimeType,            // "image/jpeg"
            'size'              => null,
            'path'              => $finalPath,           // "images/..."
            'disk'              => 's3',
            'collection'        => 'default',
            'custom_properties' => [],
        ]);

        dd($media);

        // Refresh the list so the new media is visible
        $this->loadMedia();

        // Switch to the "browse" tab so they can see the new file
        $this->mediaTab = 'browse';

        // Auto-select the new Media in our UI
        $this->selectMedia($media->id);
    }

    // Add a listener for the response from parent
    #[On('setModelValue')]
    public function handleModelValue($model, $value)
    {
        if ($model === $this->model && $value) {
            $this->selectedMediaIds = [$value];
            $this->hasSelection = true;
        }
    }

    // Keep the existing mediaSelected handler
    #[On('mediaSelected')]
    public function handleMediaSelected($model, $mediaIds)
    {
        if ($model === $this->model) {
            $this->selectedMediaIds = $mediaIds;
            $this->hasSelection = !empty($mediaIds);
        }
    }
};
?>
<div class="max-w-[320px]">
    <style>
        .media-library.has-selection .media-library-content {
            display: none !important;
        }
        .selected-media-preview {
            display: none;
        }
        .media-library.has-selection .selected-media-preview {
            display: block;
        }
        .selected-media-item {
            position: relative;
            margin-bottom: 1rem;
        }
        .unselect-button {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
        }
    </style>

    <div class="media-library {{ $hasSelection ? 'has-selection' : '' }}">

    
        <!-- Selected Media Preview Area -->
        <div class="selected-media-preview">
            @foreach($mediaItems as $media)
                @if(in_array($media['id'], $selectedMediaIds))
                    <div class="selected-media-item w-full rounded-sm">
                        <button 
                            type="button" 
                            class="unselect-button" 
                            wire:click.stop.prevent="selectMedia({{ $media['id'] }})"
                        >
                            ✕
                        </button>
                        @if(Str::startsWith($media['mime_type'], 'image/'))
                            <img
                                src="{{ Storage::disk($media['disk'])->url($media['path']) }}"
                                alt="{{ $media['name'] }}"
                                class="block w-full h-auto rounded-sm"
                            />
                        @else
                            <div class="text-center text-xs text-gray-600 p-2 border rounded-sm">
                                {{ $media['name'] }}
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        <!-- Main Media Library Content -->
        <div class="media-library-content">
            <!-- The Blade portion, using FluxUI tabs -->
            <flux:tab.group class="media-library-tabs">
                <flux:tabs variant="segmented" wire:model="mediaTab">
                    <flux:tab name="upload">Last opp</flux:tab>
                    <flux:tab name="browse" wire:click="loadMedia">Bla i bilder</flux:tab>
                </flux:tabs>

                <!-- ====== UPLOAD TAB PANEL ====== -->
                <flux:tab.panel name="upload" class="pt-0">
                    <div class="mt-0"
                         x-data="{
                            loading: false,
                            fileType: null,
                            instanceId: '{{ $instanceId }}',
                            async uploadFile() {
                                const fileInput = this.$refs.file;
                                const file = fileInput.files[0];
                                if (!file) return;

                                this.fileType = file.type;
                                this.loading = true;

                                try {
                                    if (file.type.startsWith('image/')) {
                                        // Resize images before upload
                                        const resizedFile = await this.resizeImage(file, 1200);
                                        await this.uploadToVapor(resizedFile);
                                    } else {
                                        // Other file types unmodified
                                        await this.uploadToVapor(file);
                                    }
                                } catch (error) {
                                    console.error('Upload failed:', error);
                                } finally {
                                    this.loading = false;
                                }
                            },
                            // The same Vapor.store approach
                            async uploadToVapor(file) {
                                await Vapor.store(file, { visibility: 'public-read' })
                                    .then(response => {
                                        // Update to pass parameters separately
                                        Livewire.dispatch('vaporFileUploaded', { 
                                            file: response,
                                            instanceId: this.instanceId 
                                        });
                                    });
                            },
                            resizeImage(file, maxWidth) {
                                return new Promise((resolve) => {
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        const img = new Image();
                                        img.onload = () => {
                                            const canvas = document.createElement('canvas');
                                            let width = img.width;
                                            let height = img.height;

                                            if (width > maxWidth) {
                                                height = Math.round((height * maxWidth) / width);
                                                width = maxWidth;
                                            }

                                            canvas.width = width;
                                            canvas.height = height;
                                            const ctx = canvas.getContext('2d');
                                            ctx.drawImage(img, 0, 0, width, height);

                                            canvas.toBlob((blob) => {
                                                const resizedFile = new File([blob], file.name, {
                                                    type: 'image/jpeg',
                                                    lastModified: Date.now()
                                                });
                                                resolve(resizedFile);
                                            }, 'image/jpeg', 0.8);
                                        };
                                        img.src = e.target.result;
                                    };
                                    reader.readAsDataURL(file);
                                });
                            }
                        }"
                    >
                        <!-- Dropzone area -->
                        <div
                            @drop.prevent="$refs.file.files = $event.dataTransfer.files; uploadFile()"
                            @dragover.prevent
                            class="border-2 border-dashed hover:border-zinc-300 rounded-lg p-6  flex flex-col items-center justify-center space-y-2 cursor-pointer"
                            @click="$refs.file.click()"
                        >
                            <!-- Hidden file input -->
                            <input
                                type="file"
                                x-ref="file"
                                class="hidden"
                                @change="uploadFile()"
                            />

                            <!-- Placeholder text -->
                            <p class="text-gray-600 text-sm">
                                Klikk for å laste opp
                            </p>
                        </div>

                        <!-- Loading spinner -->
                        <div x-show="loading" class="mt-3">
                            <div class="flex items-center space-x-2 text-gray-600 text-sm">
                                <svg
                                    class="animate-spin h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12" cy="12" r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    ></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8v8z"
                                    ></path>
                                </svg>
                                <span>Laster opp...</span>
                            </div>
                        </div>
                    </div>
                </flux:tab.panel>

                <!-- ====== BROWSE TAB PANEL ====== -->
                <flux:tab.panel name="browse" class="pt-0">
                    <div class="mt-0">
                        <!-- Search field -->
                        <!-- We add debounce to make searching more natural -->
                        <flux:input
                            wire:model.debounce.300ms="searchTerm"
                            wire:model.live="searchTerm"
                            placeholder="Søk i bilder"
                            class="media-library-search-input"
                        />

                        <!-- Your search input, etc. above here ... -->

                        <!-- Masonry layout container -->
                        <div class="columns-3 gap-2 mt-4 media-library-masonry">
                            @foreach($mediaItems as $media)
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
                                            src="{{ Storage::disk($media['disk'])->url($media['path']) }}"
                                            alt="{{ $media['name'] }}"
                                            class="block w-full h-auto rounded-sm"
                                        />
                                    @else
                                        <div class="text-center text-xs text-gray-600 p-2">
                                            {{ $media['name'] }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <!-- Edit Fields (only if something is selected) -->
                        @if($selectedMediaIds)
                            <div class="mt-4 p-4 bg-gray-50 border rounded-sm">
                                <flux:input
                                    wire:model="mediaEditName"
                                    label="Name"
                                    description="Give this media a friendly name."
                                />

                                <flux:input
                                    wire:model="mediaEditCollection"
                                    label="Collection"
                                    description="Group your media, e.g. 'banner' or 'gallery'."
                                />

                                <button
                                    class="mt-2 px-3 py-2 bg-blue-600 text-white rounded-sm hover:bg-blue-700"
                                    wire:click="updateMedia"
                                >
                                    Update
                                </button>
                            </div>
                        @endif
                    </div>
                </flux:tab.panel>
            </flux:tab.group>
        </div>
    </div>
</div>