<?php

namespace Tests\Feature;

use App\Console\Commands\MakeRepositoryCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MakeRepositoryCommandTest extends TestCase
{
    private Filesystem $files;

    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->projectPath = sys_get_temp_dir().'/make-repository-test-'.bin2hex(random_bytes(8));
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

        $this->assertArrayHasKey('make:repository', $commands);
    }

    public function test_it_generates_a_repository_for_a_model(): void
    {
        $tester = $this->runCommand('LoanApplication');

        $tester->assertCommandIsSuccessful();

        $path = $this->projectPath.'/app/Repositories/LoanApplicationRepository.php';
        $this->assertFileExists($path);

        $contents = $this->files->get($path);
        $this->assertStringContainsString('use App\Models\LoanApplication;', $contents);
        $this->assertStringContainsString('class LoanApplicationRepository', $contents);
        $this->assertStringContainsString('LoanApplication $loanApplication', $contents);

        $process = new Process([PHP_BINARY, '-l', $path]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }

    public function test_repository_suffix_is_not_duplicated(): void
    {
        $tester = $this->runCommand('OrderRepository');

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists($this->projectPath.'/app/Repositories/OrderRepository.php');
        $this->assertFileDoesNotExist($this->projectPath.'/app/Repositories/OrderRepositoryRepository.php');
    }

    public function test_invalid_name_does_not_create_a_repository(): void
    {
        $tester = $this->runCommand('123 order');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertDirectoryDoesNotExist($this->projectPath.'/app');
    }

    public function test_existing_repository_is_not_overwritten(): void
    {
        $path = $this->projectPath.'/app/Repositories/OrderRepository.php';
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, "<?php\n// existing\n");

        $tester = $this->runCommand('Order');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame("<?php\n// existing\n", $this->files->get($path));
    }

    private function runCommand(string $name): CommandTester
    {
        $command = new MakeRepositoryCommand($this->files, $this->projectPath);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->execute(['name' => $name]);

        return $tester;
    }
}
