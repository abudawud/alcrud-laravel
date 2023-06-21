<?php

namespace AbuDawud\AlCrudLaravel\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

trait CanManipulateFiles
{
    protected function checkForCollision(array $paths): bool
    {
        foreach ($paths as $path) {
            if ($this->fileExists($path)) {
                $this->error("$path already exists, aborting.");

                return true;
            }
        }

        return false;
    }

    private function getStub(string $stub, array $replacements = []): string
    {
        $filesystem = app(Filesystem::class);

        if (! $this->fileExists($stubPath = base_path("stubs/{$stub}.stub")) && !$this->fileExists($stubPath = $this->packagePath("stubs/{$stub}.stub"))) {
            $this->error("stub file: $stubPath not exist.");
        }

        $stub = Str::of($filesystem->get($stubPath));

        foreach ($replacements as $key => $replacement) {
            $stub = $stub->replace("{{ {$key} }}", $replacement);
        }

        $stub = (string) $stub;

        return $stub;
    }

    protected function writeStubToApp(string $stub, string $targetPath, array $replacements = []): void
    {
        $stub = $this->getStub($stub, $replacements);
        $this->writeFile($targetPath, $stub);
    }

    protected function appendStubToApp(string $stub, string $targetPath, array $replacements = []): void
    {
        $stub = $this->getStub($stub, $replacements);
        $this->writeFile($targetPath, $stub, 'APPEND');
    }

    protected function prependStubToApp(string $stub, string $targetPath, array $replacements = []): void
    {
        $stub = $this->getStub($stub, $replacements);
        $this->writeFile($targetPath, $stub, 'PREPEND');
    }

    protected function fileExists(string $path): bool
    {
        $filesystem = app(Filesystem::class);

        return $filesystem->exists($path);
    }

    protected function writeFile(string $path, string $contents, $mode = 'OVERWRITE'): void
    {
        $filesystem = app(Filesystem::class);

        $filesystem->ensureDirectoryExists(
            (string) Str::of($path)
                ->beforeLast('/'),
        );

        if ($mode == 'APPEND') {
            $filesystem->append($path, $contents);
        } elseif ($mode == 'PREPEND') {
            $filesystem->prepend($path, $contents);
        } else {
            $filesystem->put($path, $contents);
        }
    }

    private function packagePath($path)
    {
        return __DIR__ . "/../$path";
    }
}
