<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console;

trait InteractsWithStubs
{
    /**
     * NOTE: this name collides with the abstract zero-arg
     * Illuminate\Console\GeneratorCommand::getStub(). In a GeneratorCommand
     * subclass the class-declared override wins, so this method is unreachable
     * there. Nothing inside this trait may call it — use getStubPath() instead.
     */
    protected function getStub(string $file): string
    {
        $customPath = base_path('stubs/api-starter/' . $file);
        $packagePath = dirname(__DIR__, 2) . '/stubs/' . $file;

        if (file_exists($customPath)) {
            return (string) file_get_contents($customPath);
        }

        return (string) file_get_contents($packagePath);
    }

    protected function getStubPath(string $file): string
    {
        $customPath = base_path('stubs/api-starter/' . $file);

        return file_exists($customPath) ? $customPath : dirname(__DIR__, 2) . '/stubs/' . $file;
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function renderStub(string $stubFile, array $replacements): string
    {
        $content = (string) file_get_contents($this->getStubPath($stubFile));

        foreach ($replacements as $search => $replace) {
            $content = str_replace('{{' . $search . '}}', $replace, $content);
        }

        return $content;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function writeStub(string $stubFile, string $destination, array $replacements): void
    {
        $content = $this->renderStub($stubFile, $replacements);

        $this->ensureDirectoryExists(dirname($destination));
        file_put_contents($destination, $content);
    }

    protected function rootNamespace(): string
    {
        return (string) config('api-starter.namespace', 'App');
    }
}
