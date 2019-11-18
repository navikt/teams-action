<?php declare(strict_types=1);
namespace NAV\Teams;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

/**
 * @coversDefaultClass NAV\Teams\NaisDeploymentApiClient
 */
class NaisDeploymentApiClientTest extends TestCase {
    /**
     * Get a mock Guzzle Client with a history middleware
     *
     * @param Response[] $responses A list of responses to return
     * @param array $history Container for the history
     * @return HttpClient
     */
    private function getMockClient(array $responses, array &$history = []) : HttpClient {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($history));

        return new HttpClient(['handler' => $handler]);
    }

    /**
     * @covers ::provisionTeamKey
     */
    public function testCanProvisionTeamKey() : void {
        $history = [];
        $httpClient = $this->getMockClient([new Response(201)], $history);
        $this->assertTrue((new NaisDeploymentApiClient('736563726574', $httpClient))->provisionTeamKey('my-team'));
        $this->assertNotEmpty($history[0]['request']->getHeaderLine('x-nais-signature'));
    }

    /**
     * @covers ::provisionTeamKey
     */
    public function testReturnsFalseWhenKeyProvisioningFails() : void {
        $httpClient = $this->getMockClient([new Response(403)]);
        $this->assertFalse((new NaisDeploymentApiClient('736563726574', $httpClient))->provisionTeamKey('my-team'));
    }
}