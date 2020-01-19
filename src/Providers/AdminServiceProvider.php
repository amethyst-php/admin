<?php

namespace Amethyst\Providers;

use Amethyst\Core\Providers\CommonServiceProvider;

class AdminServiceProvider extends CommonServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->register(\Amethyst\Providers\DataBuilderServiceProvider::class);
        $this->app->register(\Amethyst\Providers\EventLoggerServiceProvider::class);
    }
}
