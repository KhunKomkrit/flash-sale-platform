<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
                            {name : The module name}
                            {--fields= : Comma-separated fields in name:type|rule format}';

    protected $description = 'Create a CRUD API module without a migration or factory';

    /**
     * @var array<string, string>
     */
    private const TYPE_RULES = [
        'array' => 'array',
        'boolean' => 'boolean',
        'date' => 'date',
        'datetime' => 'date',
        'decimal' => 'decimal',
        'email' => 'email',
        'integer' => 'integer',
        'json' => 'json',
        'numeric' => 'numeric',
        'string' => 'string',
        'text' => 'string',
        'url' => 'url',
        'uuid' => 'uuid',
    ];

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
            $names = $this->normalizeName((string) $this->argument('name'));
            $fields = $this->parseFields($this->option('fields'));
            $targets = $this->targetFiles($names);

            $this->assertModuleRouteLoaderIsConfigured();
            $this->assertNoCollisions($targets);

            $generatedFiles = $this->renderFiles($targets, $names, $fields);
            $this->writeAtomically($generatedFiles);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Module [{$names['class']}] created successfully.");

        return self::SUCCESS;
    }

    /**
     * @return array{class: string, uri: string, variable: string}
     */
    private function normalizeName(string $name): array
    {
        $name = trim($name);

        if ($name === '' || ! preg_match('/^[A-Za-z][A-Za-z0-9 _-]*$/', $name)) {
            throw new RuntimeException('The module name must start with a letter and contain only letters, numbers, spaces, dashes, or underscores.');
        }

        $class = Str::studly($name);

        if (
            ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)
            || in_array(strtolower($class), self::PHP_RESERVED_WORDS, true)
        ) {
            throw new RuntimeException("The module name [{$name}] cannot be normalized to a valid PHP class name.");
        }

        return [
            'class' => $class,
            'uri' => Str::kebab(Str::pluralStudly($class)),
            'variable' => Str::camel($class),
        ];
    }

    /**
     * @return list<array{name: string, storeRules: list<string>, updateRules: list<string>}>
     */
    private function parseFields(mixed $fieldOption): array
    {
        if ($fieldOption === null || trim((string) $fieldOption) === '') {
            return [];
        }

        $fields = [];
        $seen = [];

        foreach (explode(',', (string) $fieldOption) as $definition) {
            $definition = trim($definition);

            if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*):([A-Za-z]+)(?:\|(.+))?$/', $definition, $matches)) {
                throw new RuntimeException("Invalid field definition [{$definition}]. Expected name:type|rule.");
            }

            $name = $matches[1];
            $type = strtolower($matches[2]);

            if (isset($seen[$name])) {
                throw new RuntimeException("Duplicate field [{$name}].");
            }

            if (! isset(self::TYPE_RULES[$type])) {
                throw new RuntimeException("Unsupported field type [{$type}] for [{$name}].");
            }

            $rules = [self::TYPE_RULES[$type]];
            $additionalRules = isset($matches[3]) ? explode('|', $matches[3]) : [];

            foreach ($additionalRules as $rule) {
                $rule = trim($rule);

                if ($rule === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_-]*(?::[^|,]+)?$/', $rule)) {
                    throw new RuntimeException("Invalid validation rule [{$rule}] for [{$name}].");
                }

                $rules[] = $rule;
            }

            $this->assertCompatibleRules($name, $rules);

            $updateRules = array_values(array_filter(
                $rules,
                static fn (string $rule): bool => strtolower(strtok($rule, ':')) !== 'required',
            ));

            if (! $this->hasRule($updateRules, 'sometimes')) {
                array_unshift($updateRules, 'sometimes');
            }

            $fields[] = [
                'name' => $name,
                'storeRules' => $rules,
                'updateRules' => $updateRules,
            ];
            $seen[$name] = true;
        }

        return $fields;
    }

    /**
     * @param  list<string>  $rules
     */
    private function assertCompatibleRules(string $field, array $rules): void
    {
        $has = fn (string $rule): bool => $this->hasRule($rules, $rule);

        if ($has('required') && ($has('nullable') || $has('sometimes'))) {
            throw new RuntimeException("Field [{$field}] has conflicting required and nullable/sometimes rules.");
        }

        foreach (['prohibited', 'missing'] as $exclusiveRule) {
            if ($has($exclusiveRule) && ($has('required') || $has('present') || $has('filled'))) {
                throw new RuntimeException("Field [{$field}] has conflicting {$exclusiveRule} and presence rules.");
            }
        }
    }

    /**
     * @param  list<string>  $rules
     */
    private function hasRule(array $rules, string $expected): bool
    {
        foreach ($rules as $rule) {
            if (strtolower(strtok($rule, ':')) === $expected) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{class: string, uri: string, variable: string}  $names
     * @return array<string, string>
     */
    private function targetFiles(array $names): array
    {
        $class = $names['class'];

        return [
            'model' => $this->path("app/Models/{$class}.php"),
            'controller' => $this->path("app/Http/Controllers/{$class}Controller.php"),
            'storeRequest' => $this->path("app/Http/Requests/Store{$class}Request.php"),
            'updateRequest' => $this->path("app/Http/Requests/Update{$class}Request.php"),
            'resource' => $this->path("app/Http/Resources/{$class}Resource.php"),
            'service' => $this->path("app/Services/{$class}Service.php"),
            'repository' => $this->path("app/Repositories/{$class}Repository.php"),
            'route' => $this->path("routes/api/{$names['uri']}.php"),
        ];
    }

    /**
     * @param  array<string, string>  $targets
     */
    private function assertNoCollisions(array $targets): void
    {
        foreach ($targets as $target) {
            if ($this->files->exists($target)) {
                throw new RuntimeException("File [{$target}] already exists. No files were changed.");
            }
        }
    }

    private function assertModuleRouteLoaderIsConfigured(): void
    {
        $routePath = $this->path('routes/api.php');

        if (! $this->files->exists($routePath)) {
            throw new RuntimeException("The module route loader is not configured in [{$routePath}]. No files were changed.");
        }

        $routes = $this->files->get($routePath);

        if (
            ! str_contains($routes, "Route::middleware('auth:sanctum')->group")
            || ! preg_match('/glob\(\s*__DIR__\s*\.\s*[\'"]\/api\/\*\.php[\'"]\s*\)/', $routes)
            || ! preg_match('/sort\(\s*\$moduleRouteFiles\s*,\s*SORT_STRING\s*\)/', $routes)
            || ! preg_match('/require\s+\$moduleRouteFile\s*;/', $routes)
        ) {
            throw new RuntimeException("The module route loader is not configured in [{$routePath}]. Restore the routes/api.php loader before generating modules.");
        }
    }

    /**
     * @param  array<string, string>  $targets
     * @param  array{class: string, uri: string, variable: string}  $names
     * @param  list<array{name: string, storeRules: list<string>, updateRules: list<string>}>  $fields
     * @return array<string, string>
     */
    private function renderFiles(array $targets, array $names, array $fields): array
    {
        $replacements = [
            '{{ class }}' => $names['class'],
            '{{ uri }}' => $names['uri'],
            '{{ variable }}' => $names['variable'],
            '{{ fillable }}' => $this->renderFillable($fields),
            '{{ storeRules }}' => $this->renderRules($fields, 'storeRules'),
            '{{ updateRules }}' => $this->renderRules($fields, 'updateRules'),
        ];
        $rendered = [];

        foreach ($targets as $stub => $target) {
            $stubContents = $this->files->get($this->stubPath($stub));
            $rendered[$target] = str_replace(array_keys($replacements), array_values($replacements), $stubContents);
        }

        return $rendered;
    }

    /**
     * @param  list<array{name: string, storeRules: list<string>, updateRules: list<string>}>  $fields
     */
    private function renderFillable(array $fields): string
    {
        if ($fields === []) {
            return '        // TODO: Add mass-assignable fields.';
        }

        return implode("\n", array_map(
            static fn (array $field): string => "        '{$field['name']}',",
            $fields,
        ));
    }

    /**
     * @param  list<array{name: string, storeRules: list<string>, updateRules: list<string>}>  $fields
     */
    private function renderRules(array $fields, string $ruleKey): string
    {
        if ($fields === []) {
            return '            // TODO: Add validation rules before accepting input.';
        }

        return implode("\n", array_map(
            static function (array $field) use ($ruleKey): string {
                $rules = implode(', ', array_map(
                    static fn (string $rule): string => "'".str_replace("'", "\\'", $rule)."'",
                    $field[$ruleKey],
                ));

                return "            '{$field['name']}' => [{$rules}],";
            },
            $fields,
        ));
    }

    /**
     * @param  array<string, string>  $generatedFiles
     */
    private function writeAtomically(array $generatedFiles): void
    {
        $writtenFiles = [];
        $createdDirectories = [];

        try {
            foreach ($generatedFiles as $path => $contents) {
                $directory = dirname($path);

                if (! $this->files->isDirectory($directory)) {
                    $candidate = $directory;

                    while (! $this->files->isDirectory($candidate)) {
                        $createdDirectories[] = $candidate;
                        $parent = dirname($candidate);

                        if ($parent === $candidate) {
                            break;
                        }

                        $candidate = $parent;
                    }

                    $this->files->makeDirectory($directory, 0755, true);
                }

                $writtenFiles[] = $path;

                if ($this->files->put($path, $contents) === false) {
                    throw new RuntimeException("Unable to write [{$path}].");
                }
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($writtenFiles) as $path) {
                $this->files->delete($path);
            }

            usort(
                $createdDirectories,
                static fn (string $left, string $right): int => strlen($right) <=> strlen($left),
            );

            foreach (array_unique($createdDirectories) as $directory) {
                if (
                    $this->files->isDirectory($directory)
                    && count($this->files->files($directory)) === 0
                    && count($this->files->directories($directory)) === 0
                ) {
                    $this->files->deleteDirectory($directory);
                }
            }

            throw new RuntimeException('Module generation failed and all changes were rolled back.', 0, $exception);
        }
    }

    private function path(string $relativePath): string
    {
        return rtrim($this->projectBasePath ?? base_path(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .ltrim($relativePath, DIRECTORY_SEPARATOR);
    }

    private function stubPath(string $name): string
    {
        return dirname(__DIR__, 3)."/stubs/module/{$name}.stub";
    }
}
