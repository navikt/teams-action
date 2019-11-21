<?php declare(strict_types=1);
namespace NAV\Teams\Runner;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass NAV\Teams\Runner\TeamResult
 */
class TeamResultTest extends TestCase {
    public function getResults() : array {
        return [
            [
                'team-name',
                'some message',
                TeamResult::TEAM_SKIPPED,
                false,
                false,
                true,
            ],
            [
                'team-name',
                'some message',
                TeamResult::TEAM_FAILURE,
                false,
                true,
                false,
            ],
            [
                'team-name',
                'some message',
                TeamResult::TEAM_ADDED,
                true,
                false,
                false,
            ]
        ];
    }

    /**
     * @dataProvider getResults
     * @covers ::__construct
     * @covers ::getTeamName
     * @covers ::getMessage
     * @covers ::added
     * @covers ::failure
     * @covers ::skipped
     */
    public function testCanAccessProperties(string $teamName, string $message, string $result, bool $added, bool $failure, bool $skipped) : void {
        $result = new TeamResult($teamName, $message, $result);
        $this->assertSame($teamName, $result->getTeamName());
        $this->assertSame($message, $result->getMessage());
        $this->assertSame($added, $result->added(), 'Wrong return value for added()');
        $this->assertSame($failure, $result->failure(), 'Wrong return value for failure()');
        $this->assertSame($skipped, $result->skipped(), 'Wrong return value for skipped()');
    }

    /**
     * @covers ::jsonSerialize
     */
    public function testCanSerializeAsJson() : void {
        $this->assertSame(
            '[' .
            '{"team":"team-name-1","message":"some message","result":"added"},' .
            '{"team":"team-name-2","message":"some other message","result":"failure"},' .
            '{"team":"team-name-3","message":"some last message","result":"skipped"}' .
            ']',
            json_encode([
                new TeamResult('team-name-1', 'some message', TeamResult::TEAM_ADDED),
                new TeamResult('team-name-2', 'some other message', TeamResult::TEAM_FAILURE),
                new TeamResult('team-name-3', 'some last message', TeamResult::TEAM_SKIPPED),
            ])
        );
    }
}