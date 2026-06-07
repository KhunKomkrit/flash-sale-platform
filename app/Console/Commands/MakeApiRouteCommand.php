<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class MakeApiRouteCommand extends Command
{
    protected $signature = 'make:route-api
                            {name : The resource name for the API route}';

    protected $description = 'Create a modular API resource route';

    /**
     * @var list<string>
     */
    private const PHP_RESERVED_WORDS = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do',
        'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
        'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends',
        'false', 'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global',
        'goto', 'if', 'implements', 'include', 'include_once', 'instanceof',
        'insteadof', 'interface', 'isset', 'list', 'match', 'namespace', 'new',
        'null', 'or', 'print', 'private', 'protected', 'public', 'readonly',
        'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait',
        'true', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield',
    ];

    public function __construct(
        private readonly Filesystem $files,
        private readonly ?string $projectBasePath = null,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $class = $this->normalizeName((string) $this->argument('name'));
            $uri = Str::kebab(Str::pluralStudly($class));
            $target = $this->path("routes/api/{$uri}.php");

            $this->assertRouteLoaderIsConfigured();

            if ($this->files->exists($target)) {
                throw new RuntimeException("API route [{$target}] already exists.");
            }

            $contents = str_replace(
                ['{{ class }}', '{{ uri }}'],
                [$class, $uri],
                $this->files->get($this->stubPath()),
            );

            $this->files->ensureDirectoryExists(dirname($target));

            if ($this->files->put($target, $contents) === false) {
                throw new RuntimeException("Unable to write API route [{$target}].");
            }
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("API route [{$uri}] created successfully.");

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);

        if (str_ends_with(strtolower($name), 'controller')) {
            $name = substr($name, 0, -strlen('controller'));
        }

        if ($name === '' || ! preg_match('/^[A-Za-z][A-Za-z0-9 _-]*$/', $name)) {
            throw new RuntimeException('The API route name must start with a letter and contain only letters, numbers, spaces, dashes, or underscores.');
        }

        $class = Str::studly($name);

        if (
            ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)
            || in_array(strtolower($class), self::PHP_RESERVED_WORDS, true)
        ) {
            throw new RuntimeException("The API route name [{$name}] cannot be normalized to a valid PHP class name.");
        }

        return $class;
    }

    private function assertRouteLoaderIsConfigured(): void
    {
        $routePath = $this->path('routes/api.php');

        if (! $this->files->exists($routePath)) {
            throw new RuntimeException("The module route loader is not configured in [{$routePath}].");
        }

        $routes = $this->files->get($routePath);

        if (
            ! str_contains($routes, "Route::middleware('auth:sanctum')->group")
            || ! preg_match('/glob\(\s*__DIR__\s*\.\s*[\'"]\/api\/\*\.php[\'"]\s*\)/', $routes)
            || ! preg_match('/require\s+\$moduleRouteFile\s*;/', $routes)
        ) {
            throw new RuntimeException("The module route loader is not configured in [{$routePath}].");
        }
    }

    private function path(string $relativePath): string
    {
        return rtrim($this->projectBasePath ?? base_path(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .ltrim($relativePath, DIRECTORY_SEPARATOR);
    }

    private function stubPath(): string
    {
        return dirname(__DIR__, 3).'/stubs/module/route.stub';
    }
}
