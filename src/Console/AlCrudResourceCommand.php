<?php

namespace AbuDawud\AlCrudLaravel\Console;

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
    {--m|model= : model yang digunakan untuk membuat crud}
    {--p|policy= : kata kunci policy}
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
        $policyName = $this->option('policy');
        $title = $this->option('title');
        if (empty($model) || empty($policyName) || empty($title)) {
            $this->error('Opsi policy, model, dan title dibutuhkan!');

            return static::INVALID;
        }

        $modelSnake = Str::snake($model, '-');
        // check model exist
        if (!$this->fileExists(app_path("Models{$moduleAppFile}/$model.php"))) {
            $this->error('Model yg dimaksud tidak ditemukan!');

            return static::INVALID;
        }

        $controllerFile = app_path("Http/Controllers{$moduleAppFile}/{$model}Controller.php");
        $policyFile = app_path("Policies{$moduleAppFile}/{$model}Policy.php");
        $storeRequestFile = app_path("Http/Requests{$moduleAppFile}/Store{$model}Request.php");
        $updateRequestFile = app_path("Http/Requests{$moduleAppFile}/Update{$model}Request.php");
        $viewPath = resource_path("views{$moduleAppFileSnake}/{$modelSnake}");
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


        $modelClass = "App\\Models{$moduleAppClass}\\{$model}";
        $instance = new $modelClass;
        $columns = collect($instance->visible);
        $exportHtml = null;
        $exportJs = null;
        $exportPhp = null;
        $useExportClass = null;
        if ($this->option('with-export')) {
            $exportJs = str_replace([
                '{{ route_export }}',
                '{{ title }}',
            ], [
                "{$moduleRoute}{$modelSnake}",
                $title,
            ], $this->exportJs());
            $exportHtml = $this->exportHtml();
            $exportPhp = str_replace([
                '{{ model }}',
                '{{ title }}',
                '{{ routeView }}'
            ], [
                $model,
                $title,
                "{$moduleRoute}{$modelSnake}",
            ],$this->exportPhp());
            $useExportClass = implode("\n", [
                "use App\\Exports{$moduleAppClass}\\{$model}Export;",
                "use Maatwebsite\\Excel\\Facades\\Excel;",
                "use Barryvdh\DomPDF\Facade\Pdf;",
            ]);

            // Export
            $targetFile = app_path("Exports{$moduleAppFile}/{$model}Export.php");
            $this->writeStubToApp('export', $targetFile, [
                'namespace' => "App\\Exports{$moduleAppClass}",
                'class' => "{$model}Export",
                'routeView' => "{$moduleRoute}{$modelSnake}",
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
            'modelClass' => "App\\Models{$moduleAppClass}\\{$model}",
            'updateRequestClass' => "App\\Http\\Requests{$moduleAppClass}\\Update{$model}Request",
            'storeRequestClass' => "App\\Http\\Requests{$moduleAppClass}\\Store{$model}Request",
            'policyClass' => "App\\Policies{$moduleAppClass}\\{$model}Policy",
            'class' => "{$model}Controller",
            'policy' => "{$model}Policy",
            'model' => "{$model}",
            'storeRequest' => "Store{$model}Request",
            'updateRequest' => "Update{$model}Request",
            'routeView' => "{$moduleRoute}{$modelSnake}",
            'modelName' => Str::camel($model),
            'buttonMode' => $this->option('simple') ? 'modal-remote' : '',
            'title' => $title,
            'keyName' => $instance->getKeyName(),
            'exportPhp' => $exportPhp,
            'useExportClass' => $useExportClass,
        ]);

        $this->writeStubToApp('request', $updateRequestFile, [
            'namespace' => "App\\Http\\Requests{$moduleAppClass}",
            'modelClass' => "App\\Models{$moduleAppClass}\\{$model}",
            'class' => "Update{$model}Request",
            'model' => "{$model}",
        ]);

        $this->writeStubToApp('request', $storeRequestFile, [
            'namespace' => "App\\Http\\Requests{$moduleAppClass}",
            'modelClass' => "App\\Models{$moduleAppClass}\\{$model}",
            'class' => "Store{$model}Request",
            'model' => "{$model}",
        ]);

        $this->writeStubToApp('policy', $policyFile, [
            'namespace' => "App\\Policies{$moduleAppClass}",
            'modelClass' => "App\\Models{$moduleAppClass}\\{$model}",
            'modelUserClass' => 'App\\Models\\User',
            'modelUser' => 'User',
            'class' => "{$model}Policy",
            'policyName' => "{$policyName}",
            'model' => "{$model}",
        ]);

        $this->writeStubToApp('view-index', $viewIndexFile, [
            'title' => $title,
            'head' => $columns->map(fn ($str) => '<th class="text-primary">' . Str::headline($str) . '</th>')->push('<th>Actions</th>')->prepend('<th>Id</th>')->implode("\n                          "),
            'foot' => $columns->map(fn ($str) => '<th class="filter">' . Str::headline($str) . '</th>')->push('<th></th>')->prepend('<th></th>')->implode("\n                          "),
            'columns' => $columns->map(fn ($str) => ['data' => $str])->push(['data' => 'actions'])->prepend(['data' => $instance->getKeyName()])->toJson(),
            'routeView' => "{$moduleRoute}{$modelSnake}",
            'policyClass' => "App\\Policies{$moduleAppClass}\\{$model}Policy",
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
            'routeView' => "{$moduleRoute}{$modelSnake}",
        ]);

        $this->writeStubToApp('view-edit', $viewEditFile, [
            'routeView' => "{$moduleRoute}{$modelSnake}",
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
                'routeView' => "{$moduleRoute}{$modelSnake}",
                'keyName' => $instance->getKeyName(),
            ]);

            $this->prependStubToApp('view-layout-head', $viewEditFile, [
                'title' => "Update {$title}",
            ]);
            $this->appendStubToApp('view-edit-foot', $viewEditFile, [
                'title' => "Update {$title}",
                'routeView' => "{$moduleRoute}{$modelSnake}",
                'keyName' => $instance->getKeyName(),
            ]);

            $this->appendStubToApp('view-layout-footscript', $viewFormFile, []);

            $this->prependStubToApp('view-layout-head', $viewShowFile, [
                'title' => "Lihat {$title}",
            ]);
            $this->appendStubToApp('view-show-foot', $viewShowFile, [
                'title' => "Lihat {$title}",
                'routeView' => "{$moduleRoute}{$modelSnake}",
                'keyName' => $instance->getKeyName(),
            ]);
        } else {
            $this->appendStubToApp('view-layout-simple-footscript', $viewFormFile, []);
        }

        if ($this->option('with-record')) {
            foreach(['viewAny', 'view', 'update', 'create', 'delete'] as $ability) {
                $this->createPermission($policyName, $ability);
            }
            $routeModel = config('alcrud.route_model');
            $menuModel = config('alcrud.menu_model');
            $routeModel = new $routeModel;
            $route = $routeModel->create([
                'module_id' => config('alcrud.default_module_id'),
                'url' => "{$moduleAppFileSnake}{$modelSnake}",
                'can' => "{$policyName}.viewAny",
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
            $('#btn-export-excel').on('click', function (e) {
                $('#btn-export-excel').attr('disabled', true);
                const request = new XMLHttpRequest();
                const params = api.ajax.params();
                // remove limit
                delete params.start;
                delete params.limit;
                params._token = "{{ csrf_token() }}";
                request.open("POST", '{{ route("{{ route_export }}.export", ['type' => 'excel']) }}');
                request.responseType = 'blob';
                request.setRequestHeader("Content-Type", "application/json")
                request.send(JSON.stringify(params));
                request.onload = function(e) {
                    if (this.status == 200) {
                        const blob = new Blob([this.response]);
                        let a = document.createElement("a");
                        a.style = "display: none";
                        document.body.appendChild(a);
                        //Create a DOMString representing the blob and point the link element towards it
                        let url = window.URL.createObjectURL(blob);
                        a.href = url;
                        a.download = '{{ title }}.xlsx';
                        //programatically click the link to trigger the download
                        a.click();
                        //release the reference to the file by revoking the Object URL
                        window.URL.revokeObjectURL(url);
                        // remove a
                        document.body.removeChild(a);
                        $('#btn-export-excel').attr('disabled', false);
                    } else {
                        alert("Gagal export, terapkan filter agar data tidak terlalu banyak!");
                        $('#btn-export-excel').attr('disabled', false);
                    }
                }
            })

            $('#btn-export-pdf').on('click', function (e) {
                $('#btn-export-pdf').attr('disabled', true);
                const request = new XMLHttpRequest();
                const params = api.ajax.params();
                // remove limit
                delete params.start;
                delete params.limit;
                params._token = "{{ csrf_token() }}";
                request.open("POST", '{{ route("{{ route_export }}.export", ['type' => 'pdf']) }}');
                request.responseType = 'blob';
                request.setRequestHeader("Content-Type", "application/json")
                request.send(JSON.stringify(params));
                request.onload = function(e) {
                    if (this.status == 200) {
                        const blob = new Blob([this.response]);
                        let a = document.createElement("a");
                        a.style = "display: none";
                        document.body.appendChild(a);
                        //Create a DOMString representing the blob and point the link element towards it
                        let url = window.URL.createObjectURL(blob);
                        a.href = url;
                        a.download = '{{ title }}.pdf';
                        //programatically click the link to trigger the download
                        a.click();
                        //release the reference to the file by revoking the Object URL
                        window.URL.revokeObjectURL(url);
                        // remove a
                        document.body.removeChild(a);
                        $('#btn-export-pdf').attr('disabled', false);
                    } else {
                        alert("Gagal export, terapkan filter agar data tidak terlalu banyak!");
                        $('#btn-export-pdf').attr('disabled', false);
                    }
                }
            })
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
