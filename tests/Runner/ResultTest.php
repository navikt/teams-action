<?php declare(strict_types=1);
namespace NAVIT\Teams\Runner;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass NAVIT\Teams\Runner\Result
 */
class ResultTest extends TestCase {
    /**
     * @return array<string,array{0:ResultEntry[],1:string}>
     */
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
     * @param ResultEntry[] $entries
     */
    public function testCanAddEntryAndSerializeAsJson(array $entries, string $expectedJson) : void {
        $result = new Result();

        foreach ($entries as $entry) {
            $result->addEntry($entry);
        }

        $this->assertSame($expectedJson, json_encode($result), 'Incorrect JSON serialization');
    }
}