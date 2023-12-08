<?php

namespace AbuDawud\AlCrudLaravel\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AlCrudResourceCommand extends Command
{
    use CanManipulateFiles;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alcrud:resource {module?}
    {--m|model= : model class yang digunakan untuk membuat crud}
    {--c|controller= : controller name of resource}
    {--t|title= : judul form crud}
    {--simple : buat simple modal crud}
    {--with-record : tambah record otomatis}
    {--with-export : tambah record otomatis}
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
        if (empty($module)) {
            $moduleAppClass = "";
            $moduleAppFile = "";
            $moduleRoute = "";
            $moduleAppFileSnake = "";
        } else {
            $moduleAppClass = "\\" . ucfirst($module);
            $moduleAppFile = "/" . ucfirst($module);
            $moduleRoute = "{$module}.";
            $moduleAppFileSnake = "/" . Str::snake($module, '-');
        }

        $model = $this->option('model');
        $policy = str_replace('Models', 'Policies', $model) . 'Policy';
        $name = $this->option('controller');
        if (empty($name)) {
            $name = array_reverse(explode("\\", $model))[0];
        }
        $nameSnake = Str::snake($name, "-");
        $title = $this->option('title');
        if (empty($model) || empty($name) || empty($title)) {
            $this->error('Opsi name, model, dan title dibutuhkan!');

            return static::INVALID;
        }

        try {
            $instance = new $model;
            $policyInstance = new $policy;
        } catch(Exception $e){
            $this->error('Model or policy tidak ditemukan!');
            return static::INVALID;
        }

        $controllerFile = app_path("Http/Controllers{$moduleAppFile}/{$name}Controller.php");
        $storeRequestFile = app_path("Http/Requests{$moduleAppFile}/Store{$name}Request.php");
        $updateRequestFile = app_path("Http/Requests{$moduleAppFile}/Update{$name}Request.php");
        $viewPath = resource_path("views{$moduleAppFileSnake}/{$nameSnake}");
        $viewIndexFile = "{$viewPath}/index.blade.php";
        $viewCreateFile = "{$viewPath}/create.blade.php";
        $viewEditFile = "{$viewPath}/edit.blade.php";
        $viewFormFile = "{$viewPath}/form.blade.php";
        $viewShowFile = "{$viewPath}/show.blade.php";

        if (!$this->option('force') && $this->checkForCollision([
            $controllerFile,
            $policy,
            $storeRequestFile, $updateRequestFile,
            $viewIndexFile, $viewCreateFile, $viewEditFile, $viewFormFile, $viewShowFile,
        ])) {
            $this->error('Some file already exist');

            return static::INVALID;
        }

        $columns = collect($instance->visible);
        $exportHtml = null;
        $exportJs = null;
        $exportPhp = null;
        $useExportClass = null;
        $modelName = array_reverse(explode("\\", $model))[0];
        $policyName = array_reverse(explode("\\", $policy))[0];
        if ($this->option('with-export')) {
            $exportJs = str_replace([
                '{{ route_export }}',
                '{{ title }}',
            ], [
                strtolower("{$moduleRoute}{$nameSnake}"),
                $title,
            ], $this->exportJs());
            $exportHtml = $this->exportHtml();
            $exportPhp = str_replace([
                '{{ model }}',
                '{{ title }}',
                '{{ routeView }}'
            ], [
                $name,
                $title,
                strtolower("{$moduleRoute}{$nameSnake}"),
            ],$this->exportPhp());
            $useExportClass = implode("\n", [
                "use App\\Exports{$moduleAppClass}\\{$name}Export;",
                "use Maatwebsite\\Excel\\Facades\\Excel;",
                "use Barryvdh\DomPDF\Facade\Pdf;",
            ]);

            // Export
            $targetFile = app_path("Exports{$moduleAppFile}/{$name}Export.php");
            $this->writeStubToApp('export', $targetFile, [
                'namespace' => "App\\Exports{$moduleAppClass}",
                'class' => "{$name}Export",
                'routeView' => strtolower("{$moduleRoute}{$nameSnake}"),
            ]);

            // Excel template
            $targetFile = "{$viewPath}/export/excel.blade.php";
            $this->writeStubToApp('view-export-excel', $targetFile, [
                'head' => $columns->map(fn ($str) => '<th class="text-primary">' . Str::headline($str) . '</th>')->implode("\n                          "),
                'columnExport' => $columns->map(fn ($str) => '<td>{{ $record[\''. $str .'\'] }}</td>')->implode("\n          "),
            ]);

            // PDF template
            $targetFile = "{$viewPath}/export/pdf.blade.php";
            $this->writeStubToApp('view-export-pdf', $targetFile, [
                'head' => $columns->map(fn ($str) => '<th class="text-primary">' . Str::headline($str) . '</th>')->implode("\n                          "),
                'columnExport' => $columns->map(fn ($str) => '<td>{{ $record[\''. $str .'\'] }}</td>')->implode("\n          "),
                'title' => $title,
            ]);
        }

        $this->writeStubToApp('controller', $controllerFile, [
            'namespace' => "App\\Http\\Controllers{$moduleAppClass}",
            'modelClass' => $model,
            'updateRequestClass' => "App\\Http\\Requests{$moduleAppClass}\\Update{$name}Request",
            'storeRequestClass' => "App\\Http\\Requests{$moduleAppClass}\\Store{$name}Request",
            'columns' => $columns->map(fn ($str) => "\"{\$table}.$str\"")->prepend("\"{\$table}.".$instance->getKeyName().'"')->implode(","),
            'policyClass' => $policy,
            'class' => "{$name}Controller",
            'policy' => $policyName,
            'model' => $modelName,
            'storeRequest' => "Store{$name}Request",
            'updateRequest' => "Update{$name}Request",
            'routeView' => strtolower("{$moduleRoute}{$nameSnake}"),
            'modelName' => Str::camel($modelName),
            'buttonMode' => $this->option('simple') ? 'modal-remote' : '',
            'title' => $title,
            'keyName' => $instance->getKeyName(),
            'exportPhp' => $exportPhp,
            'useExportClass' => $useExportClass,
        ]);

        $this->writeStubToApp('request', $updateRequestFile, [
            'namespace' => "App\\Http\\Requests{$moduleAppClass}",
            'modelClass' => $model,
            'class' => "Update{$name}Request",
            'model' => $modelName,
        ]);

        $this->writeStubToApp('request', $storeRequestFile, [
            'namespace' => "App\\Http\\Requests{$moduleAppClass}",
            'modelClass' => $model,
            'class' => "Store{$name}Request",
            'model' => $modelName,
        ]);


        $this->writeStubToApp('view-index', $viewIndexFile, [
            'title' => $title,
            'head' => $columns->map(fn ($str) => '<th class="text-primary">' . Str::headline($str) . '</th>')->push('<th>Actions</th>')->prepend('<th>Id</th>')->implode("\n                          "),
            'foot' => $columns->map(fn ($str) => '<th class="filter">' . Str::headline($str) . '</th>')->push('<th></th>')->prepend('<th></th>')->implode("\n                          "),
            'columns' => $columns->map(fn ($str) => ['data' => $str])->push(['data' => 'actions'])->prepend(['data' => $instance->getKeyName()])->toJson(),
            'routeView' => strtolower("{$moduleRoute}{$nameSnake}"),
            'policyClass' => $policy,
            'buttonMode' => $this->option('simple') ? 'modal-remote' : '',
            'exportHtml' => $exportHtml,
            'exportJs' => $exportJs,
        ]);

        $this->writeStubToApp('view-show', $viewShowFile, [
            'rowInfo' => collect($instance->visible)->map(function ($field) {
                return '
                <tr>
                    <th width="30%">' . Str::headline($field) . '</th>
                    <td>{{ $record->' . $field . ' }}</td>
                </tr>';
            })->implode(""),
        ]);

        $this->writeStubToApp('view-create', $viewCreateFile, [
            'routeView' => strtolower("{$moduleRoute}{$nameSnake}"),
        ]);

        $this->writeStubToApp('view-edit', $viewEditFile, [
            'routeView' => strtolower("{$moduleRoute}{$nameSnake}"),
        ]);

        $this->writeStubToApp('view-form', $viewFormFile, [
            'formField' => collect($instance->visible)->map(function ($field) {
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
                'routeView' => strtolower("{$moduleRoute}{$nameSnake}"),
                'keyName' => $instance->getKeyName(),
            ]);

            $this->prependStubToApp('view-layout-head', $viewEditFile, [
                'title' => "Update {$title}",
            ]);
            $this->appendStubToApp('view-edit-foot', $viewEditFile, [
                'title' => "Update {$title}",
                'routeView' => strtolower("{$moduleRoute}{$nameSnake}"),
                'keyName' => $instance->getKeyName(),
            ]);

            $this->appendStubToApp('view-layout-footscript', $viewFormFile, []);

            $this->prependStubToApp('view-layout-head', $viewShowFile, [
                'title' => "Lihat {$title}",
            ]);
            $this->appendStubToApp('view-show-foot', $viewShowFile, [
                'title' => "Lihat {$title}",
                'routeView' => strtolower("{$moduleRoute}{$nameSnake}"),
                'keyName' => $instance->getKeyName(),
            ]);
        } else {
            $this->appendStubToApp('view-layout-simple-footscript', $viewFormFile, []);
        }

        if ($this->option('with-record')) {
            $policyKey = $policyInstance::POLICY_NAME;
            foreach(['viewAny', 'view', 'update', 'create', 'delete'] as $ability) {
                $this->createPermission($policyKey, $ability);
            }
            $routeModel = config('alcrud.route_model');
            $menuModel = config('alcrud.menu_model');
            $routeModel = new $routeModel;
            $route = $routeModel->create([
                'module_id' => config('alcrud.default_module_id'),
                'url' => "{$moduleAppFileSnake}{$nameSnake}",
                'can' => "{$policyKey}.viewAny",
                'active' => true,
            ]);
            $menuModel = new $menuModel;
            $menuModel->create([
                'module_id' => config('alcrud.default_module_id'),
                'role_id' => config('alcrud.default_role_id'),
                'route_id' => $route->id,
                'text' => $title,
            ]);
        }
    }

    private function exportHtml() {
        return <<<HTML
            <div class="btn-group">
              <a class="btn btn-default dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <span class="fas fa-file-export"></span> Export Data
              </a>

              <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                <button id="btn-export-excel" title="Export Excel" class="dropdown-item"> <span class="fas fa-file-excel"></span> Excel</button>
                <button id="btn-export-pdf" title="Export PDF" class="dropdown-item"> <span class="fas fa-file-pdf"></span> PDF</button>
              </div>
            </div>
        HTML;
    }

    private function exportJs() {
        return <<<JS
            function downloadInNewTab(url) {
                const a = document.createElement('a');
                a.href = url;
                a.target = '_blank'; // Open in a new tab
                a.rel = 'noopener noreferrer'; // No referrer for security reasons
                document.body.appendChild(a); // Temporarily add to the DOM
                a.click(); // Trigger a click
                document.body.removeChild(a); // Remove from the DOM
            }

            $('#btn-export-excel').on('click', function(e) {
                const params = api.ajax.params();
                delete params.start;
                delete params.limit;

                downloadInNewTab("{{ route('{{ route_export }}.export') }}" + "?type=excel&" + $.param(
                    params));
            });
            $('#btn-export-pdf').on('click', function(e) {
                const params = api.ajax.params();
                delete params.start;
                delete params.limit;

                downloadInNewTab("{{ route('{{ route_export }}.export') }}" + "?type=pdf&" + $.param(
                    params));
            });
        JS;
    }

    private function exportPhp() {
        return '
        public function export(Request $request)
        {
            if ($request->get("type") == "excel") {
                return $this->exportExcel();
            } else if ($request->get("type") == "pdf") {
                return $this->exportPdf();
            } else {
                abort(403, "Unknown filetype!");
            }
        }

        private function exportExcel()
        {
            $data = $this->buildDatatable($this->buildQuery())->toArray();
            $exporter = new {{ model }}Export($data["data"]);
            return Excel::download($exporter, "{{ title }}.xlsx");
        }

        public function exportPdf()
        {
            $data = $this->buildDatatable($this->buildQuery())->toArray();
            $pdf = Pdf::loadView("{{ routeView }}.export.pdf", [
                "records" => $data["data"],
            ]);
            $pdf->setPaper("a4");
            $pdf->addInfo([
                "Title" => "{{ title }}",
                "Author" => config("app.company_name"),
                "Subject" => config("app.name"),
            ]);
            return $pdf->stream("{{ title }}.pdf");
        }';
    }

    private function createPermission($policy, $ability) {
        $permission = Permission::create([
            'name' => "{$policy}.{$ability}",
        ]);
        $defaultRole = Role::findById(config('alcrud.default_role_id'));
        if ($defaultRole) {
            $defaultRole->givePermissionTo($permission);
        }
    }
}
