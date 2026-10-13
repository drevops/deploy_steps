<?php

declare(strict_types=1);

namespace Drupal\Tests\deploy_steps\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Drupal\deploy_steps\DeployStepInterface;
use Drupal\deploy_steps\DeployStepRunner;
use Drupal\deploy_steps\Drush\Listeners\DeployStepListener;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the DeployStepListener Console event listeners.
 *
 * @group DeployStep
 */
#[Group('DeployStep')]
class DeployStepListenerTest extends UnitTestCase {

  /**
   * Tests that create() injects the runner service from the container.
   */
  public function testCreate(): void {
    $runner = $this->createMock(DeployStepRunner::class);
    $runner->expects($this->once())->method('run')->with(DeployStepInterface::PHASE_PRE);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())->method('get')->with(DeployStepRunner::class)->willReturn($runner);

    $listener = DeployStepListener::create($container);
    $listener->runPreDeploySteps(new ConsoleCommandEvent(new Command('deploy:hook'), new ArrayInput([]), new NullOutput()));
  }

  /**
   * Tests that only a Console deploy:hook command runs the PRE phase.
   *
   * @dataProvider dataProviderRunPreDeploySteps
   */
  #[DataProvider('dataProviderRunPreDeploySteps')]
  public function testRunPreDeploySteps(Command $command, bool $expected): void {
    $runner = $this->createMock(DeployStepRunner::class);
    $runner->expects($expected ? $this->once() : $this->never())->method('run')->with(DeployStepInterface::PHASE_PRE);

    $listener = new DeployStepListener($runner);
    $listener->runPreDeploySteps(new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput()));
  }

  /**
   * Data provider for testRunPreDeploySteps().
   */
  public static function dataProviderRunPreDeploySteps(): \Iterator {
    yield 'console deploy:hook' => [new Command('deploy:hook'), TRUE];
    yield 'annotated deploy:hook' => [new AnnotatedCommand('deploy:hook'), FALSE];
    yield 'other command' => [new Command('cache:rebuild'), FALSE];
  }

  /**
   * Tests that only a successful Console deploy:hook runs the POST phase.
   *
   * @dataProvider dataProviderRunPostDeploySteps
   */
  #[DataProvider('dataProviderRunPostDeploySteps')]
  public function testRunPostDeploySteps(Command $command, int $exit_code, bool $expected): void {
    $runner = $this->createMock(DeployStepRunner::class);
    $runner->expects($expected ? $this->once() : $this->never())->method('run')->with(DeployStepInterface::PHASE_POST);

    $listener = new DeployStepListener($runner);
    $listener->runPostDeploySteps(new ConsoleTerminateEvent($command, new ArrayInput([]), new NullOutput(), $exit_code));
  }

  /**
   * Data provider for testRunPostDeploySteps().
   */
  public static function dataProviderRunPostDeploySteps(): \Iterator {
    yield 'console deploy:hook succeeded' => [new Command('deploy:hook'), Command::SUCCESS, TRUE];
    yield 'console deploy:hook failed' => [new Command('deploy:hook'), Command::FAILURE, FALSE];
    yield 'annotated deploy:hook' => [new AnnotatedCommand('deploy:hook'), Command::SUCCESS, FALSE];
    yield 'other command' => [new Command('cache:rebuild'), Command::SUCCESS, FALSE];
  }

}
