<?php

namespace AbuDawud\AlCrudLaravel;

use AbuDawud\AlCrudLaravel\Console\AlCrudModelCommand;
use AbuDawud\AlCrudLaravel\Console\AlCrudResourceCommand;
use AbuDawud\AlCrudLaravel\Views\Components\ICheck;
use AbuDawud\AlCrudLaravel\Views\Components\Modal;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class AlCrudLaravelServiceProvider extends BaseServiceProvider
{
    /**
     * The prefix to use for register/load the package resources.
     *
     * @var string
     */
    protected $pkgPrefix = 'alcrud';

    /**
     * Bootstrap the package's services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViews();
        $this->loadComponents();
        $this->registerCommands();
        $this->loadConfig();
    }

    /**
     * Load the package views.
     *
     * @return void
     */
    private function loadViews()
    {
        $viewsPath = $this->packagePath('resources/views');
        $this->loadViewsFrom($viewsPath, $this->pkgPrefix);
    }

    private function loadComponents() {
        $this->loadViewComponentsAs($this->pkgPrefix, [
            'modal' => Modal::class,
            'i-check' => ICheck::class,
        ]);
    }

    private function loadConfig()
    {
        $configPath = $this->packagePath('config/alcrud.php');
        $this->mergeConfigFrom($configPath, $this->pkgPrefix);
    }

    /**
     * Get the absolute path to some package resource.
     *
     * @param  string  $path  The relative path to the resource
     * @return string
     */
    private function packagePath($path)
    {
        return __DIR__ . "/../$path";
    }

    /**
     * Register the package's artisan commands.
     *
     * @return void
     */
    private function registerCommands()
    {
        $this->commands([
            AlCrudResourceCommand::class,
            AlCrudModelCommand::class,
        ]);
    }
}
