<?php

namespace Fineide\LivewirePagebuilder\Commands;

use Illuminate\Console\Command;

class LivewirePagebuilderCommand extends Command
{
    public $signature = 'livewire-pagebuilder';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
