<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Support;

class UserModelPatcher
{
    /**
     * Ensure the configured User model uses HasApiTokens.
     *
     * @return array{status: string, path?: string, message: string}
     */
    public static function ensureHasApiTokens(?string $userModel = null): array
    {
        $userModel ??= (string) config('api-starter.auth.user_model', 'App\\Models\\User');

        if (! class_exists(\Laravel\Sanctum\HasApiTokens::class) && ! trait_exists(\Laravel\Sanctum\HasApiTokens::class)) {
            return [
                'status' => 'missing_sanctum',
                'message' => 'laravel/sanctum not installed. Run: composer require laravel/sanctum',
            ];
        }

        if (class_exists($userModel) && in_array(\Laravel\Sanctum\HasApiTokens::class, class_uses_recursive($userModel), true)) {
            return [
                'status' => 'ok',
                'message' => "{$userModel} already uses HasApiTokens",
            ];
        }

        $path = self::resolveModelPath($userModel);

        if ($path === null || ! is_file($path)) {
            return [
                'status' => 'not_found',
                'message' => "User model file not found for {$userModel}. Add HasApiTokens manually.",
            ];
        }

        $contents = (string) file_get_contents($path);

        if (str_contains($contents, 'HasApiTokens')) {
            return [
                'status' => 'ok',
                'path' => $path,
                'message' => "{$path} already references HasApiTokens",
            ];
        }

        if (! str_contains($contents, 'use Laravel\\Sanctum\\HasApiTokens;')) {
            if (preg_match('/^namespace\s+[^;]+;/m', $contents, $m, PREG_OFFSET_CAPTURE)) {
                $offset = $m[0][1] + strlen($m[0][0]);
                $contents = substr($contents, 0, $offset)
                    . "\n\nuse Laravel\\Sanctum\\HasApiTokens;"
                    . substr($contents, $offset);
            }
        }

        if (! preg_match('/^class\s+\w+/m', $contents, $classMatch, PREG_OFFSET_CAPTURE)) {
            return [
                'status' => 'failed',
                'path' => $path,
                'message' => "Could not locate class declaration in {$path}",
            ];
        }

        $classStart = $classMatch[0][1];
        $bracePos = strpos($contents, '{', $classStart);
        if ($bracePos === false) {
            return [
                'status' => 'failed',
                'path' => $path,
                'message' => "Could not locate class body in {$path}",
            ];
        }

        $body = substr($contents, $bracePos + 1);

        if (preg_match('/^(\s*)use\s+([^;]+);/m', $body, $bodyUse, PREG_OFFSET_CAPTURE)) {
            $traits = array_map('trim', explode(',', $bodyUse[2][0]));
            if (! in_array('HasApiTokens', $traits, true)) {
                array_unshift($traits, 'HasApiTokens');
                $newUse = $bodyUse[1][0] . 'use ' . implode(', ', $traits) . ';';
                $absolute = $bracePos + 1 + $bodyUse[0][1];
                $contents = substr($contents, 0, $absolute)
                    . $newUse
                    . substr($contents, $absolute + strlen($bodyUse[0][0]));
            }
        } else {
            $contents = substr($contents, 0, $bracePos + 1)
                . "\n    use HasApiTokens;\n"
                . substr($contents, $bracePos + 1);
        }

        file_put_contents($path, $contents);

        return [
            'status' => 'patched',
            'path' => $path,
            'message' => "Added HasApiTokens to {$path}",
        ];
    }

    protected static function resolveModelPath(string $userModel): ?string
    {
        $relative = str_replace('\\', '/', $userModel);

        if (str_starts_with($relative, 'App/')) {
            $path = app_path(substr($relative, 4) . '.php');

            return is_file($path) ? $path : null;
        }

        $path = base_path($relative . '.php');

        return is_file($path) ? $path : null;
    }
}
