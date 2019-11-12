<?php

declare(strict_types=1);

/*
 * This file is part of the HubKit package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace HubKit\Tests\Handler;

use HubKit\Cli\Handler\UpMergeHandler;
use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;

class UpMergeHandlerTest extends TestCase
{
    use SymfonyStyleTrait;

    /** @var ObjectProphecy */
    private $process;
    /** @var ObjectProphecy */
    private $git;
    /** @var ObjectProphecy */
    private $github;

    /** @before */
    public function setUpCommandHandler()
    {
        $this->git = $this->prophesize(Git::class);
        $this->git->guardWorkingTreeReady()->willReturn(null);

        $this->github = $this->prophesize(GitHub::class);
        $this->github->getHostname()->willReturn('github.com');
        $this->github->getOrganization()->willReturn('park-manager');
        $this->github->getRepository()->willReturn('hubkit');

        $this->process = $this->prophesize(CliProcess::class);
    }

    /** @test */
    public function it_merges_current_branch_into_next_version_branch()
    {
        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();
        $this->git->checkoutRemoteBranch('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.5'], true)->shouldBeCalled();

        $this->executeHandler();

        $this->assertOutputMatches('Merged "2.3" into "2.5"');
    }

    /** @test */
    public function it_merges_current_branch_into_next_relative_version_branch()
    {
        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.x']);

        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();
        $this->git->checkoutRemoteBranch('upstream', '2.x')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.x')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.x'], true)->shouldBeCalled();

        $this->executeHandler();

        $this->assertOutputMatches('Merged "2.3" into "2.x"');
    }

    /** @test */
    public function it_merges_to_master_when_current_branch_is_last_version()
    {
        $this->git->getActiveBranchName()->willReturn('2.6');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        $this->git->ensureBranchInSync('upstream', '2.6')->shouldBeCalled();
        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.6'])->shouldBeCalled();

        $this->git->checkout('2.6')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['master'], true)->shouldBeCalled();

        $this->executeHandler();

        $this->assertOutputMatches('Merged "2.6" into "master"');
    }

    /** @test */
    public function it_does_nothing_when_current_branch_is_not_a_version()
    {
        $this->git->getActiveBranchName()->willReturn('master');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();
        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        $this->executeHandler();
    }

    /** @test */
    public function it_merges_custom_branch_into_next_version_branch()
    {
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);
        $this->git->checkoutRemoteBranch('upstream', '2.3')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.5'], true)->shouldBeCalled();

        $this->executeHandler($this->getArgs()->setArgument('branch', '2.3'));

        $this->assertOutputMatches('Merged "2.3" into "2.5"');
    }

    /** @test */
    public function it_merges_current_branch_into_next_version_branches()
    {
        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6', '2.x']);

        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.6')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.6')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.5'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.x')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.x')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.6'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.x'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.5', '2.6', '2.x', 'master'], true)->shouldBeCalled();

        $this->executeHandler($this->getArgs()->setOption('all', true));

        $this->assertOutputMatches([
            'Merged "2.3" into "2.5"',
            'Merged "2.5" into "2.6"',
            'Merged "2.6" into "2.x"',
            'Merged "2.x" into "master"',
        ]);
    }

    /** @test */
    public function it_does_nothing_with_all_when_current_branch_is_not_a_version()
    {
        $this->git->getActiveBranchName()->willReturn('master');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();
        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        $this->executeHandler($this->getArgs()->setOption('all', true));
    }

    /** @test */
    public function error_message_contains_original_exception_message()
    {
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);
        $this->git->checkoutRemoteBranch('upstream', '2.3')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.3')->willThrow(
            new \RuntimeException('Local branch is not up-to-date.')
        );

        $this->executeHandler($this->getArgs()->setArgument('branch', '2.3'));
        $this->assertOutputMatches('Local branch is not up-to-date.');
    }

    /** @test */
    public function it_dry_merges_current_branch_into_next_version_branch()
    {
        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);
        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();

        $this->executeHandler($this->getArgs()->setOption('dry-run', true));

        $this->assertOutputMatches('[DRY-RUN] Merged "2.3" into "2.5"');
    }

    /** @test */
    public function it_dry_merges_current_branch_into_next_version_branches()
    {
        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6', '2.x']);
        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.6')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.x')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();

        $this->executeHandler($this->getArgs()->setOption('all', true)->setOption('dry-run', true));

        $this->assertOutputMatches([
            '[DRY-RUN] Merged "2.3" into "2.5"',
            '[DRY-RUN] Merged "2.5" into "2.6"',
            '[DRY-RUN] Merged "2.6" into "2.x"',
            '[DRY-RUN] Merged "2.x" into "master"',
        ]);
    }

    private function getArgs(): Args
    {
        $format = ArgsFormat::build()
            ->addOption(new Option('all', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addOption(new Option('dry-run', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addArgument(new Argument('branch', Argument::OPTIONAL | Argument::STRING))
            ->getFormat()
        ;

        return new Args($format);
    }

    private function executeHandler(Args $args = null)
    {
        $style = $this->createStyle();

        $handler = new UpMergeHandler($style, $this->git->reveal(), $this->github->reveal(), $this->process->reveal());
        $handler->handle($args ?? $this->getArgs());
    }
}
