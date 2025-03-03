<?php

namespace Fineide\LivewirePagebuilder;

use Fineide\LivewirePagebuilder\View\Components\BlockEditor;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class LivewirePagebuilderServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register views directory
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'livewire-pagebuilder');

        // Mount Volt Components
        if (class_exists(Volt::class)) {
            // Mount the views directory for Volt
            Volt::mount([
                __DIR__.'/../resources/views/livewire',
                __DIR__.'/../resources/views/blocks',
            ]);

            // Register the route
            Volt::route('/admin/pagebuilder/{id}', 'pagebuilder')
                ->middleware(['web'])
                ->name('pagebuilder.edit');

            Volt::route('/admin/pagebuilder/preview/{id}', 'preview')
                ->middleware(['web'])
                ->name('pagebuilder.preview');
        }

        // Publish views and config separately
        $this->publishes([
            __DIR__.'/../resources/views/livewire' => resource_path('views/livewire'),
            __DIR__.'/../resources/views/blocks' => resource_path('views/livewire/blocks'),
        ], 'pagebuilder-views');

        $this->publishes([
            __DIR__.'/../config/pagebuilder.php' => config_path('pagebuilder.php'),
        ], 'pagebuilder-config');

        // Publish all assets
        $this->publishes([
            __DIR__.'/../resources/views/livewire' => resource_path('views/livewire'),
            __DIR__.'/../resources/views/blocks' => resource_path('views/livewire/blocks'),
            __DIR__.'/../config/pagebuilder.php' => config_path('pagebuilder.php'),
        ], 'pagebuilder');

        Blade::component('livewire-pagebuilder::block-editor', BlockEditor::class);
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/pagebuilder.php', 'pagebuilder'
        );
    }
}
