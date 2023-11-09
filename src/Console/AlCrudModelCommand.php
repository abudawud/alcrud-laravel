<?php

namespace AbuDawud\AlCrudLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
    {--p|policy= : nama policy}
    {--with-migration : buat file migrasi}
    {--with-policy : buat file policy}
    {--t|table= : nama tabel}
    {--c|connection= : nama koneksi}
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
        $parentModel = array_reverse(explode("\\", $parentModelClass))[0];

        $model = $this->option('model');
        if (empty($model)) {
            $this->error('nama model dibutuhkan!');

            return static::INVALID;
        }

        if (empty($module)) {
            $moduleAppClass = "";
            $moduleAppFile = "";
        } else {
            $moduleAppClass = "\\" . ucfirst($module);
            $moduleAppFile = "/" . ucfirst($module);
        }

        $modelFile = app_path("Models{$moduleAppFile}/{$model}.php");
        if (! $this->option('force') && $this->checkForCollision([
            $modelFile,
        ])) {
            $this->error('Model already exist');

            return static::INVALID;
        }

        $tableName = null;
        $fillable = null;
        if ($tableName = $this->option('table')) {
            $sm = DB::connection($this->option('connection'))->getDoctrineSchemaManager();
            if ($columns = $sm->listTableColumns($tableName)) {
                $fillable = [];
                foreach(array_keys($columns) as $col) {
                    if (in_array($col, ['id', 'created_at', 'updated_at'])) {
                        continue;
                    }

                    $col = str_replace('`', '', $col);
                    $fillable[] = "'{$col}'";
                }
                $fillable = implode(",\n        ", $fillable);
            }
        }

        $this->writeStubToApp('model', $modelFile, [
            'namespace' => "App\\Models{$moduleAppClass}",
            'class' => $model,
            'tableName' => $tableName,
            'fillable' => $fillable,
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

        if ($this->option('with-policy')) {
            $policy = $this->option('policy');
            if (empty($policy)) {
                $this->error('key policy dibutuhkan!');

                return static::INVALID;
            }
            $this->call('alcrud:policy', [
                '-m' => $model,
                'module' => $module,
                '-p' => $policy,
            ]);
        }
    }
}
