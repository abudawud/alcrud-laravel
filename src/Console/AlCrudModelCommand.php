<?php

namespace AbuDawud\AlCrudLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AlCrudModelCommand extends Command
{
    use CanManipulateFiles;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alcrud:model {module?}
    {--m|model= : nama model}
    {--with-migration : buat file migrasi}
    {--force : timpa file jika sudah ada}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate model';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $module = $this->argument('module');
        $parentModelClass = config('alcrud.parent_model');
        $parentModel = end(explode("\\", $parentModelClass));

        $model = $this->option('model');
        if (empty($model)) {
            $this->error('nama model dibutuhkan!');

            return static::INVALID;
        }

        if (empty($module)) {
            $moduleAppClass = "";
            $moduleAppFile = "";
        } else {
            $moduleAppClass = "\\{$module}";
            $moduleAppFile = "/{$module}";
        }

        $modelFile = app_path("Models{$moduleAppFile}/{$model}.php");
        if (! $this->option('force') && $this->checkForCollision([
            $modelFile,
        ])) {
            $this->error('Model already exist');

            return static::INVALID;
        }

        $this->writeStubToApp('model', $modelFile, [
            'namespace' => "App\\Models{$moduleAppClass}",
            'class' => $model,
            'parentModelClass' => $parentModelClass,
            'parentModel' => $parentModel,
        ]);

        if ($this->option('with-migration')) {
            $table = Str::of($model)->plural()->snake();
            $this->call('make:migration', [
                'name' => "create_{$table}_table",
                '--path' => "database/migrations{$moduleAppFile}",
            ]);
        }
    }
}
