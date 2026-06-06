<?php

namespace Tests\Feature;

use App\Console\Commands\MakeModuleCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MakeModuleCommandTest extends TestCase
{
    private Filesystem $files;

    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->projectPath = sys_get_temp_dir().'/make-module-test-'.bin2hex(random_bytes(8));
        $this->files->makeDirectory($this->projectPath.'/routes', 0755, true);
        $this->files->put(
            $this->projectPath.'/routes/api.php',
            "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/health', fn () => ['ok' => true]);\n"
        );
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->projectPath);

        parent::tearDown();
    }

    public function test_command_is_discovered_by_laravel(): void
    {
        $commands = $this->app->make(Kernel::class)->all();

        $this->assertArrayHasKey('make:module', $commands);
    }

    public function test_it_generates_a_complete_module_with_fields_and_a_sanctum_route(): void
    {
        $tester = $this->runCommand([
            'name' => 'LoanApplication',
            '--fields' => 'amount:decimal|required,status:string|nullable',
        ]);

        $tester->assertCommandIsSuccessful();

        $expectedFiles = [
            'app/Models/LoanApplication.php',
            'app/Http/Controllers/LoanApplicationController.php',
            'app/Http/Requests/StoreLoanApplicationRequest.php',
            'app/Http/Requests/UpdateLoanApplicationRequest.php',
            'app/Http/Resources/LoanApplicationResource.php',
            'app/Services/LoanApplicationService.php',
            'app/Repositories/LoanApplicationRepository.php',
        ];

        foreach ($expectedFiles as $relativePath) {
            $path = $this->projectPath.'/'.$relativePath;
            $this->assertFileExists($path);

            $process = new Process([PHP_BINARY, '-l', $path]);
            $process->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        }

        $model = $this->files->get($this->projectPath.'/app/Models/LoanApplication.php');
        $storeRequest = $this->files->get($this->projectPath.'/app/Http/Requests/StoreLoanApplicationRequest.php');
        $updateRequest = $this->files->get($this->projectPath.'/app/Http/Requests/UpdateLoanApplicationRequest.php');
        $controller = $this->files->get($this->projectPath.'/app/Http/Controllers/LoanApplicationController.php');
        $routes = $this->files->get($this->projectPath.'/routes/api.php');

        $this->assertStringContainsString("'amount',", $model);
        $this->assertStringContainsString("'status',", $model);
        $this->assertStringContainsString("'amount' => ['decimal', 'required']", $storeRequest);
        $this->assertStringContainsString("'status' => ['string', 'nullable']", $storeRequest);
        $this->assertStringContainsString("'amount' => ['sometimes', 'decimal']", $updateRequest);
        $this->assertStringContainsString("'status' => ['sometimes', 'string', 'nullable']", $updateRequest);
        $this->assertStringContainsString('function show(LoanApplication $loanApplication)', $controller);
        $this->assertStringContainsString('Response::HTTP_CREATED', $controller);
        $this->assertStringContainsString('return response()->noContent();', $controller);
        $this->assertStringContainsString('use App\Http\Controllers\LoanApplicationController;', $routes);
        $this->assertStringContainsString("Route::middleware('auth:sanctum')->group", $routes);
        $this->assertSame(1, substr_count($routes, "Route::apiResource('loan-applications'"));
    }

    public function test_it_generates_a_safe_skeleton_without_fields(): void
    {
        $tester = $this->runCommand(['name' => 'AuditLog']);

        $tester->assertCommandIsSuccessful();

        $model = $this->files->get($this->projectPath.'/app/Models/AuditLog.php');
        $storeRequest = $this->files->get($this->projectPath.'/app/Http/Requests/StoreAuditLogRequest.php');
        $updateRequest = $this->files->get($this->projectPath.'/app/Http/Requests/UpdateAuditLogRequest.php');

        $this->assertStringContainsString('// TODO: Add mass-assignable fields.', $model);
        $this->assertStringContainsString('// TODO: Add validation rules before accepting input.', $storeRequest);
        $this->assertStringContainsString('// TODO: Add validation rules before accepting input.', $updateRequest);
        $this->assertStringNotContainsString('$request->all()', $this->files->get(
            $this->projectPath.'/app/Http/Controllers/AuditLogController.php'
        ));
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function test_invalid_input_aborts_without_changes(string $name, ?string $fields): void
    {
        $originalRoutes = $this->files->get($this->projectPath.'/routes/api.php');
        $input = ['name' => $name];

        if ($fields !== null) {
            $input['--fields'] = $fields;
        }

        $tester = $this->runCommand($input);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame($originalRoutes, $this->files->get($this->projectPath.'/routes/api.php'));
        $this->assertDirectoryDoesNotExist($this->projectPath.'/app');
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function invalidInputProvider(): iterable
    {
        yield 'invalid module name' => ['123 loan', null];
        yield 'invalid field syntax' => ['Loan', 'amount'];
        yield 'duplicate fields' => ['Loan', 'amount:decimal,amount:numeric'];
        yield 'invalid identifier' => ['Loan', 'total.amount:decimal'];
        yield 'unsupported type' => ['Loan', 'amount:money'];
        yield 'conflicting modifiers' => ['Loan', 'status:string|required|nullable'];
    }

    public function test_file_collision_aborts_before_writing_any_other_file_or_route(): void
    {
        $this->files->makeDirectory($this->projectPath.'/app/Models', 0755, true);
        $this->files->put($this->projectPath.'/app/Models/Order.php', "<?php\n// existing\n");
        $originalRoutes = $this->files->get($this->projectPath.'/routes/api.php');

        $tester = $this->runCommand(['name' => 'Order', '--fields' => 'total:numeric|required']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame("<?php\n// existing\n", $this->files->get($this->projectPath.'/app/Models/Order.php'));
        $this->assertFileDoesNotExist($this->projectPath.'/app/Http/Controllers/OrderController.php');
        $this->assertSame($originalRoutes, $this->files->get($this->projectPath.'/routes/api.php'));
    }

    public function test_route_collision_aborts_before_writing_files(): void
    {
        $routePath = $this->projectPath.'/routes/api.php';
        $this->files->put(
            $routePath,
            $this->files->get($routePath)."\nRoute::apiResource('orders', ExistingController::class);\n"
        );
        $originalRoutes = $this->files->get($routePath);

        $tester = $this->runCommand(['name' => 'Order']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertDirectoryDoesNotExist($this->projectPath.'/app');
        $this->assertSame($originalRoutes, $this->files->get($routePath));
    }

    public function test_write_failure_rolls_back_files_directories_and_routes(): void
    {
        $routePath = $this->projectPath.'/routes/api.php';
        $originalRoutes = $this->files->get($routePath);
        $failingFiles = new class extends Filesystem
        {
            public function put($path, $contents, $lock = false)
            {
                if (str_ends_with($path, '/routes/api.php') && str_contains($contents, 'Route::apiResource')) {
                    parent::put($path, "<?php\n// partial write\n", $lock);

                    throw new \RuntimeException('Simulated route write failure.');
                }

                return parent::put($path, $contents, $lock);
            }
        };
        $command = new MakeModuleCommand($failingFiles, $this->projectPath);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $tester->execute(['name' => 'Order']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame($originalRoutes, $this->files->get($routePath));
        $this->assertDirectoryDoesNotExist($this->projectPath.'/app');
    }

    /**
     * @param  array<string, string>  $input
     */
    private function runCommand(array $input): CommandTester
    {
        $command = new MakeModuleCommand($this->files, $this->projectPath);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }
}
