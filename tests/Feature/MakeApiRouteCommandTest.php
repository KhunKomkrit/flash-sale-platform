<?php

namespace Tests\Feature;

use App\Console\Commands\MakeApiRouteCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MakeApiRouteCommandTest extends TestCase
{
    private Filesystem $files;

    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->projectPath = sys_get_temp_dir().'/make-api-route-test-'.bin2hex(random_bytes(8));
        $this->files->makeDirectory($this->projectPath.'/routes', 0755, true);
        $this->files->put(
            $this->projectPath.'/routes/api.php',
            <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    $moduleRouteFiles = glob(__DIR__.'/api/*.php') ?: [];

    foreach ($moduleRouteFiles as $moduleRouteFile) {
        require $moduleRouteFile;
    }
});
PHP
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

        $this->assertArrayHasKey('make:route-api', $commands);
    }

    public function test_it_generates_a_plural_api_resource_route(): void
    {
        $tester = $this->runCommand('LoanApplication');

        $tester->assertCommandIsSuccessful();

        $path = $this->projectPath.'/routes/api/loan-applications.php';
        $this->assertFileExists($path);

        $contents = $this->files->get($path);
        $this->assertStringContainsString('use App\Http\Controllers\LoanApplicationController;', $contents);
        $this->assertStringContainsString(
            "Route::apiResource('loan-applications', LoanApplicationController::class);",
            $contents
        );

        $process = new Process([PHP_BINARY, '-l', $path]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }

    public function test_controller_suffix_is_not_duplicated(): void
    {
        $tester = $this->runCommand('OrderController');

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists($this->projectPath.'/routes/api/orders.php');
    }

    public function test_missing_route_loader_does_not_create_a_route(): void
    {
        $this->files->put($this->projectPath.'/routes/api.php', "<?php\n");

        $tester = $this->runCommand('Order');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertDirectoryDoesNotExist($this->projectPath.'/routes/api');
    }

    public function test_existing_route_is_not_overwritten(): void
    {
        $path = $this->projectPath.'/routes/api/orders.php';
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, "<?php\n// existing\n");

        $tester = $this->runCommand('Order');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame("<?php\n// existing\n", $this->files->get($path));
    }

    private function runCommand(string $name): CommandTester
    {
        $command = new MakeApiRouteCommand($this->files, $this->projectPath);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->execute(['name' => $name]);

        return $tester;
    }
}
