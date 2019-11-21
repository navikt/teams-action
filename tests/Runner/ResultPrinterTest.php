<?php declare(strict_types=1);
namespace NAV\Teams\Runner;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass NAV\Teams\Runner\ResultPrinter
 */
class ResultPrinterTest extends TestCase {
    /**
     * @covers ::print
     */
    public function testCanPrintResults() : void {
        $expectedOutput = <<<OUTPUT
team-name-1: Created
team-name-2: (error) Could not create GitHub team
team-name-3: (skipped) Group exists in Azure AD
team-name-4: Created
OUTPUT;

        $this->expectOutputString($expectedOutput);
        (new ResultPrinter())->print([
            new TeamResult('team-name-1', 'Team added', TeamResult::TEAM_ADDED),
            new TeamResult('team-name-2', 'Could not create GitHub team', TeamResult::TEAM_FAILURE),
            new TeamResult('team-name-3', 'Group exists in Azure AD', TeamResult::TEAM_SKIPPED),
            new TeamResult('team-name-4', 'Team added', TeamResult::TEAM_ADDED),
        ]);
    }
}