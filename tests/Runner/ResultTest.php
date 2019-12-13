<?php declare(strict_types=1);
namespace NAV\Teams\Runner;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass NAV\Teams\Runner\Result
 */
class ResultTest extends TestCase {
    public function getEntries() : array {
        $entryWithGroupId = new ResultEntry('some-team');
        $entryWithGroupId->setGroupId('group-id');

        return [
            'no entries' => [
                [],
                '[]'
            ],
            'with entries' => [
                [
                    new ResultEntry('team'),
                    $entryWithGroupId,
                ],
                '[{"teamName":"team","groupId":null},{"teamName":"some-team","groupId":"group-id"}]'
            ],
        ];
    }

    /**
     * @dataProvider getEntries
     * @covers ::addEntry
     * @covers ::jsonSerialize
     */
    public function testCanAddEntryAndSerializeAsJson(array $entries, string $expectedJson) : void {
        $result = new Result();

        foreach ($entries as $entry) {
            $result->addEntry($entry);
        }

        $this->assertSame($expectedJson, json_encode($result), 'Incorrect JSON serialization');
    }

    /**
     * @covers ::isFailure
     * @covers ::fail
     */
    public function testCanMarkResultAsFailed() : void {
        $result = new Result();
        $this->assertFalse($result->isFailure(), 'Did not expect result to be a failure');
        $result->fail();
        $this->assertTrue($result->isFailure(), 'Expected result to be a failure');
    }
}