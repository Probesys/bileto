<?php

namespace App\Tests;

use App\Entity;
use App\Tests;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\NullOutput;

class MigrationsTest extends KernelTestCase
{
    use Tests\DatabaseResetterHelper;

    /** @var Application */
    private $application;

    /**
     * @before
     */
    public function setupApplication(): void
    {
        $kernel = self::createKernel();
        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);
    }

    /**
     * @after
     */
    public function resetDatabase(): void
    {
        $output = new NullOutput();

        // make sure to reset the database structure to its initial state
        $input = new StringInput('doctrine:schema:drop --force');
        $result = $this->application->run($input, $output);
        $this->assertSame(Command::SUCCESS, $result);

        $input = new StringInput('doctrine:schema:create');
        $result = $this->application->run($input, $output);
        $this->assertSame(Command::SUCCESS, $result);
    }

    public function testMigrationsUpWork(): void
    {
        $output = new NullOutput();

        // erase the database structure
        $input = new StringInput('doctrine:schema:drop --force');
        $result = $this->application->run($input, $output);
        $this->assertSame(Command::SUCCESS, $result);

        // and apply the migrations one by one
        $input = new StringInput('doctrine:migrations:migrate --no-interaction');
        $result = $this->application->run($input, $output);
        $this->assertSame(Command::SUCCESS, $result);
    }

    public function testMigrationsDownWork(): void
    {
        $output = new NullOutput();

        $firstMigration = 'DoctrineMigrations\\\\Version20220928142616CreateUser';
        $input = new StringInput("doctrine:migrations:migrate {$firstMigration} --no-interaction");
        $result = $this->application->run($input, $output);
        $this->assertSame(Command::SUCCESS, $result);
    }
}
