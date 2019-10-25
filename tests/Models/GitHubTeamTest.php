<?php declare(strict_types=1);
namespace NAV\Teams\Models;

use NAV\Teams\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass NAV\Teams\Models\GitHubTeam
 */
class GitHubTeamTest extends TestCase {
    /**
     * @covers ::fromArray
     * @covers ::getId
     * @covers ::getName
     * @covers ::__construct
     */
    public function testCanCreateFromArray() : void {
        $team = GitHubTeam::fromArray(['id' => 123, 'name' => 'some-name']);
        $this->assertSame(123, $team->getId());
        $this->assertSame('some-name', $team->getName());
    }

    /**
     * @covers ::fromArray
     */
    public function testCanValidateInput() : void {
        $this->expectExceptionObject(new InvalidArgumentException('Missing data element: id'));
        GitHubTeam::fromArray(['name' => 'name']);
    }
}