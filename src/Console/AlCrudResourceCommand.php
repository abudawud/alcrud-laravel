<?php

namespace AbuDawud\AlCrudLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AlCrudResourceCommand extends Command
{
    use CanManipulateFiles;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alcrud:resource {module}
    {--m|model= : model yang digunakan untuk membuat crud}
    {--p|policy= : kata kunci policy}
    {--t|title= : judul form crud}
    {--simple : buat simple modal crud}
    {--force : timpa file jika sudah ada}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate resource';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $module = $this->argument('module');
        $moduleApp = Str::ucfirst($module);

        $model = $this->option('model');
        $policyName = $this->option('policy');
        $title = $this->option('title');
        if (empty($model) || empty($policyName) || empty($title)) {
            $this->error('Opsi policy, model, dan title dibutuhkan!');

            return static::INVALID;
        }

        $modelSnake = Str::snake($model, '-');
        // check model exist
        if (!$this->fileExists(app_path("Models/{$moduleApp}/$model.php"))) {
            $this->error('Model yg dimaksud tidak ditemukan!');

            return static::INVALID;
        }

        $controllerFile = app_path("Http/Controllers/{$moduleApp}/{$model}Controller.php");
        $policyFile = app_path("Policies/{$moduleApp}/{$model}Policy.php");
        $storeRequestFile = app_path("Http/Requests/{$moduleApp}/Store{$model}Request.php");
        $updateRequestFile = app_path("Http/Requests/{$moduleApp}/Update{$model}Request.php");
        $viewPath = resource_path("views/{$module}/{$modelSnake}");
        $viewIndexFile = "{$viewPath}/index.blade.php";
        $viewCreateFile = "{$viewPath}/create.blade.php";
        $viewEditFile = "{$viewPath}/edit.blade.php";
        $viewFormFile = "{$viewPath}/form.blade.php";
        $viewShowFile = "{$viewPath}/show.blade.php";

        if (!$this->option('force') && $this->checkForCollision([
            $controllerFile,
            $policyFile,
            $storeRequestFile, $updateRequestFile,
            $viewIndexFile, $viewCreateFile, $viewEditFile, $viewFormFile, $viewShowFile,
        ])) {
            $this->error('Some file already exist');

            return static::INVALID;
        }

        $modelClass = "App\\Models\\{$moduleApp}\\{$model}";
        $instance = new $modelClass;
        $this->writeStubToApp('controller', $controllerFile, [
            'namespace' => "App\\Http\\Controllers\\{$moduleApp}",
            'modelClass' => "App\\Models\\{$moduleApp}\\{$model}",
            'updateRequestClass' => "App\\Http\\Requests\\{$moduleApp}\\Update{$model}Request",
            'storeRequestClass' => "App\\Http\\Requests\\{$moduleApp}\\Store{$model}Request",
            'policyClass' => "App\\Policies\\{$moduleApp}\\{$model}Policy",
            'class' => "{$model}Controller",
            'policy' => "{$model}Policy",
            'model' => "{$model}",
            'storeRequest' => "Store{$model}Request",
            'updateRequest' => "Update{$model}Request",
            'routeView' => "{$module}.{$modelSnake}",
            'modelName' => Str::camel($model),
            'buttonMode' => $this->option('simple') ? 'modal-remote' : '',
            'title' => $title,
            'keyName' => $instance->getKeyName(),
        ]);

        $this->writeStubToApp('request', $updateRequestFile, [
            'namespace' => "App\\Http\\Requests\\{$moduleApp}",
            'modelClass' => "App\\Models\\{$moduleApp}\\{$model}",
            'class' => "Update{$model}Request",
            'model' => "{$model}",
        ]);

        $this->writeStubToApp('request', $storeRequestFile, [
            'namespace' => "App\\Http\\Requests\\{$moduleApp}",
            'modelClass' => "App\\Models\\{$moduleApp}\\{$model}",
            'class' => "Store{$model}Request",
            'model' => "{$model}",
        ]);

        $this->writeStubToApp('policy', $policyFile, [
            'namespace' => "App\\Policies\\{$moduleApp}",
            'modelClass' => "App\\Models\\{$moduleApp}\\{$model}",
            'modelUserClass' => 'App\\Models\\User',
            'modelUser' => 'User',
            'class' => "{$model}Policy",
            'policyName' => "{$policyName}",
            'model' => "{$model}",
        ]);

        $columns = collect($instance->displayable);
        $this->writeStubToApp('view-index', $viewIndexFile, [
            'title' => $title,
            'head' => $columns->map(fn ($str) => '<th class="text-primary">' . Str::headline($str) . '</th>')->push('<th>Actions</th>')->prepend('<th>Id</th>')->implode("\n                          "),
            'foot' => $columns->map(fn ($str) => '<th class="filter">' . Str::headline($str) . '</th>')->push('<th></th>')->prepend('<th></th>')->implode("\n                          "),
            'columns' => $columns->map(fn ($str) => ['data' => $str])->push(['data' => 'actions'])->prepend(['data' => $instance->getKeyName()])->toJson(),
            'routeView' => "{$module}.{$modelSnake}",
            'policyClass' => "App\\Policies\\{$moduleApp}\\{$model}Policy",
            'buttonMode' => $this->option('simple') ? 'modal-remote' : '',
        ]);

        $this->writeStubToApp('view-show', $viewShowFile, [
            'rowInfo' => collect($instance->displayable)->map(function ($field) {
                return '
                <tr>
                    <th width="30%">' . Str::headline($field) . '</th>
                    <td>{{ $record->' . $field . ' }}</td>
                </tr>';
            })->implode(""),
        ]);

        $this->writeStubToApp('view-create', $viewCreateFile, [
            'routeView' => "{$module}.{$modelSnake}",
        ]);

        $this->writeStubToApp('view-edit', $viewEditFile, [
            'routeView' => "{$module}.{$modelSnake}",
        ]);

        $this->writeStubToApp('view-form', $viewFormFile, [
            'formField' => collect($instance->displayable)->map(function ($field) {
                return '
    <div class="col-md-6 form-group">
        {!! Form::label("' . $field . '", "' . Str::headline($field) . '") !!}
        {!! Form::text("' . $field . '", $record?->' . $field . ', ["class" => "form-control"]) !!}
    </div>';
            })->implode(""),
        ]);

        if (!$this->option('simple')) {
            // create blade
            $this->prependStubToApp('view-layout-head', $viewCreateFile, [
                'title' => "Tambah {$title}",
            ]);
            $this->appendStubToApp('view-create-foot', $viewCreateFile, [
                'title' => "Tambah {$title}",
                'routeView' => "{$module}.{$modelSnake}",
                'keyName' => $instance->getKeyName(),
            ]);

            $this->prependStubToApp('view-layout-head', $viewEditFile, [
                'title' => "Update {$title}",
            ]);
            $this->appendStubToApp('view-edit-foot', $viewEditFile, [
                'title' => "Update {$title}",
                'routeView' => "{$module}.{$modelSnake}",
                'keyName' => $instance->getKeyName(),
            ]);

            $this->appendStubToApp('view-layout-footscript', $viewFormFile, []);

            $this->prependStubToApp('view-layout-head', $viewShowFile, [
                'title' => "Lihat {$title}",
            ]);
            $this->appendStubToApp('view-show-foot', $viewShowFile, [
                'title' => "Lihat {$title}",
                'routeView' => "{$module}.{$modelSnake}",
                'keyName' => $instance->getKeyName(),
            ]);
        }
    }
}
