<?php

namespace Tests\Feature;

use App\Console\Commands\MakeServiceCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MakeServiceCommandTest extends TestCase
{
    private Filesystem $files;

    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->projectPath = sys_get_temp_dir().'/make-service-test-'.bin2hex(random_bytes(8));
        $this->files->makeDirectory($this->projectPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->projectPath);

        parent::tearDown();
    }

    public function test_command_is_discovered_by_laravel(): void
    {
        $commands = $this->app->make(Kernel::class)->all();

        $this->assertArrayHasKey('make:service', $commands);
    }

    public function test_it_generates_a_service_for_a_model(): void
    {
        $tester = $this->runCommand('LoanApplication');

        $tester->assertCommandIsSuccessful();

        $path = $this->projectPath.'/app/Services/LoanApplicationService.php';
        $this->assertFileExists($path);

        $contents = $this->files->get($path);
        $this->assertStringContainsString('use App\Repositories\LoanApplicationRepository;', $contents);
        $this->assertStringContainsString('class LoanApplicationService', $contents);
        $this->assertStringContainsString('LoanApplication $loanApplication', $contents);

        $process = new Process([PHP_BINARY, '-l', $path]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }

    public function test_service_suffix_is_not_duplicated(): void
    {
        $tester = $this->runCommand('OrderService');

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists($this->projectPath.'/app/Services/OrderService.php');
        $this->assertFileDoesNotExist($this->projectPath.'/app/Services/OrderServiceService.php');
    }

    public function test_invalid_name_does_not_create_a_service(): void
    {
        $tester = $this->runCommand('123 order');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertDirectoryDoesNotExist($this->projectPath.'/app');
    }

    public function test_existing_service_is_not_overwritten(): void
    {
        $path = $this->projectPath.'/app/Services/OrderService.php';
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, "<?php\n// existing\n");

        $tester = $this->runCommand('Order');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame("<?php\n// existing\n", $this->files->get($path));
    }

    private function runCommand(string $name): CommandTester
    {
        $command = new MakeServiceCommand($this->files, $this->projectPath);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->execute(['name' => $name]);

        return $tester;
    }
}
