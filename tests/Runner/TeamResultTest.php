<?php declare(strict_types=1);
namespace NAV\Teams\Runner;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass NAV\Teams\Runner\TeamResult
 */
class TeamResultTest extends TestCase {
    /**
     * @covers ::__construct
     * @covers ::getTeamName
     * @covers ::skipped
     * @covers ::failed
     * @covers ::getMessage
     * @covers ::setGroupId
     * @covers ::getGroupId
     * @covers ::added
     * @covers ::jsonSerialize
     */
    public function testSuccessfulResult() : void {
        $this->assertSame(json_encode([
            'team' => 'name',
            'added' => true,
            'skipped' => false,
            'failed' => false,
            'groupId' => 'group-id',
            'message' => null,
        ]), json_encode((new TeamResult('name'))->setGroupId('group-id')));
    }

    /**
     * @covers ::__construct
     * @covers ::getTeamName
     * @covers ::skip
     * @covers ::skipped
     * @covers ::failed
     * @covers ::getMessage
     * @covers ::getGroupId
     * @covers ::added
     * @covers ::jsonSerialize
     */
    public function testSkippedResult() : void {
        $this->assertSame(json_encode([
            'team' => 'name',
            'added' => false,
            'skipped' => true,
            'failed' => false,
            'groupId' => null,
            'message' => 'some message',
        ]), json_encode((new TeamResult('name'))->skip('some message')));
    }

    /**
     * @covers ::__construct
     * @covers ::getTeamName
     * @covers ::skipped
     * @covers ::fail
     * @covers ::failed
     * @covers ::getMessage
     * @covers ::getGroupId
     * @covers ::added
     * @covers ::jsonSerialize
     */
    public function testFailedResult() : void {
        $this->assertSame(json_encode([
            'team' => 'name',
            'added' => false,
            'skipped' => false,
            'failed' => true,
            'groupId' => null,
            'message' => 'some message',
        ]), json_encode((new TeamResult('name'))->fail('some message')));
    }
}