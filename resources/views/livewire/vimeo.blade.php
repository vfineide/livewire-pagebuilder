<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Vimeo\Vimeo as VimeoClient;

new #[Layout('components.layouts.app.guest')] class extends Component 
{
    public $videos = [];
    
    public function mount()
    {
        try {
            $client = new VimeoClient(
                env('VIMEO_CLIENT_ID'),
                env('VIMEO_CLIENT_SECRET'),
                env('VIMEO_ACCESS_TOKEN')
            );
            
            $response = $client->request('/me/videos', [
                'per_page' => 50, // Reduced to improve performance
                'fields' => 'uri,name,description,duration,created_time,pictures.sizes',
                'sort' => 'date'
            ], 'GET');
            
            if ($response['status'] === 200) {
                $this->videos = $response['body']['data'];
            }
        } catch (\Exception $e) {
            $this->videos = [];
        }
    }
}
?>

<div class="container mx-auto p-4">
    <h2 class="text-2xl font-bold mb-6">Videoer</h2>
    
    @if(empty($videos))
        <p class="text-gray-600">Ingen videoer funnet.</p>
    @else
        <div class="space-y-4">
            @foreach($videos as $video)
                <div class="bg-white rounded-lg shadow-sm p-4 flex items-start gap-4">
                    @if(!empty($video['pictures']['sizes']))
                        <img 
                            src="{{ end($video['pictures']['sizes'])['link'] }}" 
                            alt="{{ $video['name'] }}"
                            class="w-48 h-27 object-cover rounded-sm"
                        >
                    @endif
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold mb-2">{{ $video['name'] }}</h3>
                        @if(!empty($video['description']))
                            <p class="text-gray-600 text-sm mb-2">
                                {{ \Illuminate\Support\Str::limit($video['description'], 200) }}
                            </p>
                        @endif
                        <div class="text-sm text-gray-500">
                            <span>Duration: {{ gmdate("H:i:s", $video['duration']) }}</span>
                            <span class="mx-2">•</span>
                            <span>Created: {{ \Carbon\Carbon::parse($video['created_time'])->format('M d, Y') }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
