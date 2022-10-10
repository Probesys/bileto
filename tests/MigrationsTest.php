<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\NullOutput;
use Zenstruck\Foundry\Test\ResetDatabase;

class MigrationsTest extends KernelTestCase
{
    use ResetDatabase;

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
