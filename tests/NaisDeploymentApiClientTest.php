<?php declare(strict_types=1);
namespace NAVIT\Teams;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\{
    Client as HttpClient,
    Exception\ClientException,
    Handler\MockHandler,
    HandlerStack,
    Psr7\Response,
    Psr7\Request,
    Middleware,
};

/**
 * @coversDefaultClass NAVIT\Teams\NaisDeploymentApiClient
 */
class NaisDeploymentApiClientTest extends TestCase {
    /**
     * Get a mock Guzzle Client with a history middleware
     *
     * @param Response[] $responses A list of responses to return
     * @param array<array{response:Response,request:Request}> $history Container for the history
     * @param-out array<array{response:Response,request:Request}> $history
     * @return HttpClient
     */
    private function getMockClient(array $responses, array &$history = []) : HttpClient {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($history));

        return new HttpClient(['handler' => $handler]);
    }

    /**
     * @covers ::__construct
     * @covers ::provisionTeamKey
     */
    public function testCanProvisionTeamKey() : void {
        $history = [];
        $httpClient = $this->getMockClient([new Response(201)], $history);
        (new NaisDeploymentApiClient('736563726574', $httpClient))->provisionTeamKey('my-team');
        $this->assertNotEmpty($history[0]['request']->getHeaderLine('x-nais-signature'));
    }

    /**
     * @covers ::provisionTeamKey
     */
    public function testReturnsFalseWhenKeyProvisioningFails() : void {
        $httpClient = $this->getMockClient([new Response(403)]);
        $this->expectException(ClientException::class);
        (new NaisDeploymentApiClient('736563726574', $httpClient))->provisionTeamKey('my-team');
    }
}