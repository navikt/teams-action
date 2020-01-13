<?php declare(strict_types=1);
namespace NAV\Teams\Models;

use NAV\Teams\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass NAV\Teams\Models\AzureAdGroupMember
 */
class AzureAdGroupMemberTest extends TestCase {
    public function getCreationData() : array {
        return [
            'all elements present' => [
                'some-id',
                'some name',
                'mail@nav.no'
            ],
        ];
    }

    /**
     * @covers ::fromArray
     * @covers ::getId
     * @covers ::getDisplayName
     * @covers ::getMail
     * @covers ::__construct
     * @dataProvider getCreationData
     */
    public function testCanCreateFromArray(string $id, string $displayName, string $mail) : void {
        $group = AzureAdGroupMember::fromArray([
            'id'          => $id,
            'displayName' => $displayName,
            'mail'        => $mail,
        ]);
        $this->assertInstanceOf(AzureAdGroupMember::class, $group);
        $this->assertSame($id, $group->getId());
        $this->assertSame($displayName, $group->getDisplayName());
        $this->assertSame($mail, $group->getMail());
    }

    public function getInvalidData() : array {
        return [
            'missing id' => [
                [
                    'displayName' => 'name',
                ],
                'Missing data element: id'
            ],
            'missing display name' => [
                [
                    'id' => 'some-id',
                ],
                'Missing data element: displayName'
            ],
        ];
    }

    /**
     * @covers ::fromArray
     * @dataProvider getInvalidData
     */
    public function testCanValidateInput(array $data, string $errorMessage) : void {
        $this->expectExceptionObject(new InvalidArgumentException($errorMessage));
        AzureAdGroupMember::fromArray($data);
    }
}