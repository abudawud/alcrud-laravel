<?php

namespace AbuDawud\AlCrudLaravel\Console;

use Illuminate\Console\Command;

class AlCrudPolicyCommand extends Command
{
    use CanManipulateFiles;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alcrud:policy {module?}
    {--m|model= : model yang digunakan untuk membuat crud}
    {--p|policy= : kata kunci policy}
    {--force : timpa file jika sudah ada}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate policy';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $module = $this->argument('module');
        if (empty($module)) {
            $moduleAppClass = "";
            $moduleAppFile = "";
        } else {
            $moduleAppClass = "\\" . ucfirst($module);
            $moduleAppFile = "/" . ucfirst($module);
        }

        $model = $this->option('model');
        $policyName = $this->option('policy');
        if (empty($model) || empty($policyName)) {
            $this->error('Opsi policy, model, dan title dibutuhkan!');

            return static::INVALID;
        }

        if (!$this->fileExists(app_path("Models{$moduleAppFile}/$model.php"))) {
            $this->error('Model yg dimaksud tidak ditemukan!');

            return static::INVALID;
        }

        $policyFile = app_path("Policies{$moduleAppFile}/{$model}Policy.php");

        if (!$this->option('force') && $this->checkForCollision([
            $policyFile,
        ])) {
            $this->error('Some file already exist');

            return static::INVALID;
        }

        $this->writeStubToApp('policy', $policyFile, [
            'namespace' => "App\\Policies{$moduleAppClass}",
            'modelClass' => "App\\Models{$moduleAppClass}\\{$model}",
            'modelUserClass' => 'App\\Models\\User',
            'modelUser' => 'User',
            'class' => "{$model}Policy",
            'policyName' => "{$policyName}",
            'model' => "{$model}",
        ]);

    }
}
