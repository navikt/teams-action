<?php declare(strict_types=1);
namespace NAVIT\Teams\Runner;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass NAVIT\Teams\Runner\ResultEntry
 */
class ResultEntryTest extends TestCase {
    /**
     * @covers ::__construct
     * @covers ::setGroupId
     * @covers ::jsonSerialize
     */
    public function testCanSerializeAsJson() : void {
        $entry = new ResultEntry('team-name');
        $this->assertSame('{"teamName":"team-name","groupId":null}', json_encode($entry), 'Incorrect JSON representation');
        $entry->setGroupId('group-id');
        $this->assertSame('{"teamName":"team-name","groupId":"group-id"}', json_encode($entry), 'Incorrect JSON representation');
    }
}