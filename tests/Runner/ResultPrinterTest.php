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
team-name-1: Created (Azure AD object ID: group-id)
team-name-2: (error) Could not create GitHub team
team-name-3: (skipped) Group exists in Azure AD
OUTPUT;

        $this->expectOutputString($expectedOutput);
        (new ResultPrinter())->print([
            (new TeamResult('team-name-1'))->setGroupId('group-id'),
            (new TeamResult('team-name-2'))->fail('Could not create GitHub team'),
            (new TeamResult('team-name-3'))->skip('Group exists in Azure AD'),
        ]);
    }
}