<?php

namespace Fineide\LivewirePagebuilder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Fineide\LivewirePagebuilder\LivewirePagebuilder
 */
class LivewirePagebuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Fineide\LivewirePagebuilder\LivewirePagebuilder::class;
    }
}
