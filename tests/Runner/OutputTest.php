<?php declare(strict_types=1);
namespace NAVIT\Teams\Runner;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass NAVIT\Teams\Runner\Output
 */
class OutputTest extends TestCase {
    public function getMessages() : array {
        return [
            [
                'team',
                'some message',
                '[team] some message' . PHP_EOL,
            ],
            [
                'team',
                ' some message ' . PHP_EOL . PHP_EOL,
                '[team] some message' . PHP_EOL,
            ],
        ];
    }

    /**
     * @dataProvider getMessages
     * @covers ::debug
     * @covers ::normalize
     */
    public function testCanOutputDebugMessage(string $team, string $rawMessage, string $output) : void {
        $this->expectOutputString($output);
        (new Output())->debug($team, $rawMessage);
    }

    /**
     * @dataProvider getMessages
     * @covers ::failure
     * @covers ::normalize
     */
    public function testCanOutputFailureMessage(string $team, string $rawMessage, string $output) : void {
        $this->expectOutputString($output);
        (new Output())->failure($team, $rawMessage);
    }

    /**
     * @covers ::hasFailures
     * @covers ::debug
     * @covers ::failure
     */
    public function testCanCheckForFailures() : void {
        $output = new Output();
        $this->assertFalse($output->hasFailures(), 'Did not expect failures');
        $this->expectOutputString('[team] some message' . PHP_EOL);
        $output->failure('team', 'some message');
        $this->assertTrue($output->hasFailures(), 'Expected failures');
    }
}