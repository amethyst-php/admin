<?php

namespace Railken\Amethyst\Providers;

use Railken\Amethyst\Common\CommonServiceProvider;

class AdminServiceProvider extends CommonServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->register(\Railken\Amethyst\Providers\DataBuilderServiceProvider::class);
        $this->app->register(\Railken\Amethyst\Providers\EventLoggerServiceProvider::class);
    }
}
