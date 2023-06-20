<?php

namespace AbuDawud\AlCrudLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AlCrudModel extends Command
{
    use CanManipulateFiles;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alcrud:model {module}
    {--m|model= : nama model}
    {--with-migration : buat file migrasi}
    {--force : timpa file jika sudah ada}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Buat model';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $module = $this->argument('module');

        $model = $this->option('model');
        if (empty($model)) {
            $this->error('nama model dibutuhkan!');

            return static::INVALID;
        }

        $moduleApp = Str::ucfirst($module);
        $modelFile = app_path("Models/{$moduleApp}/{$model}.php");
        if (! $this->option('force') && $this->checkForCollision([
            $modelFile,
        ])) {
            $this->error('Model already exist');

            return static::INVALID;
        }

        $this->writeStubToApp('model', $modelFile, [
            'namespace' => "App\\Models\\{$moduleApp}",
            'class' => $model,
            'parentModelClass' => "App\\Models\\{$moduleApp}Model",
            'parentModel' => "{$moduleApp}Model",
        ]);

        if ($this->option('with-migration')) {
            $table = Str::of($model)->plural()->snake();
            $this->call('make:migration', [
                'name' => "create_{$table}_table",
                '--path' => "database/migrations/{$module}",
            ]);
        }
    }
}
