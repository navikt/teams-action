<?php declare(strict_types=1);
namespace NAV\Teams;

use NAV\Teams\Models\GitHubTeam;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Middleware;
use NAV\Teams\Models\AzureAdGroup;

/**
 * @coversDefaultClass NAV\Teams\GitHubApiClient
 */
class GitHubClientTest extends TestCase {
    private function getMockClient(array $responses, array &$history = []) : HttpClient {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($history));

        return new HttpClient(['handler' => $handler]);
    }

    /**
     * @covers ::getTeam
     */
    public function testCanGetTeam() : void {
        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [new Response(200, [], '{"id": 123, "name": "team"}')],
            $clientHistory
        );

        $githubTeam = (new GitHubApiClient('access-token', $httpClient))->getTeam('team');

        $this->assertCount(1, $clientHistory, 'Expected one request');
        $this->assertInstanceOf(GitHubTeam::class, $githubTeam);
        $this->assertSame('orgs/navikt/teams/team', (string) $clientHistory[0]['request']->getUri());
    }

    /**
     * @covers ::getTeam
     */
    public function testReturnsNullWhenTeamIsNotFound() : void {
        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [new ClientException(
                'team not found',
                new Request('GET', 'orgs/navikt/teams/team'),
                new Response(404)
            )],
            $clientHistory
        );

        $this->assertNull((new GitHubApiClient('access-token', $httpClient))->getTeam('team'));
    }

    /**
     * @covers ::getTeam
     */
    public function testThrowsExceptionOnErrors() : void {
        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [new ClientException(
                'Forbidden',
                new Request('GET', 'orgs/navikt/teams/team'),
                new Response(403)
            )],
            $clientHistory
        );

        $this->expectException(ClientException::class);
        (new GitHubApiClient('access-token', $httpClient))->getTeam('team');
    }

    /**
     * @covers ::createTeam
     */
    public function testCanCreateTeam() : void {
        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [new Response(201, [], '{"id": 123, "name": "team-name"}')],
            $clientHistory
        );

        $githubTeam = (new GitHubApiClient('access-token', $httpClient))->createTeam('team-name');

        $this->assertInstanceOf(GitHubTeam::class, $githubTeam);
        $this->assertCount(1, $clientHistory, 'Expected one request');

        $request = $clientHistory[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('orgs/navikt/teams', (string) $request->getUri());

        $body = json_decode($request->getBody()->getContents(), true);

        $this->assertSame('team-name', $body['name'], 'Team name not correct');
    }

    /**
     * @covers ::syncTeamAndGroup
     */
    public function testTeamSync() : void {
        $team = $this->createConfiguredMock(GitHubTeam::class, [
            'getId' => 123,
        ]);
        $aadGroup = $this->createConfiguredMock(AzureAdGroup::class, [
            'getId'          => '123abc',
            'getDisplayName' => 'group name',
            'getDescription' => 'group description',
        ]);

        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [new Response(201)],
            $clientHistory
        );

        $this->assertTrue((new GitHubApiClient('access-token', $httpClient))->syncTeamAndGroup($team, $aadGroup));

        $this->assertCount(1, $clientHistory, 'Expected one request');

        $request = $clientHistory[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('teams/123/team-sync/group-mappings', (string) $request->getUri());
        $body = json_decode($request->getBody()->getContents(), true);

        $this->assertSame([
            'groups' => [
                [
                    'group_id'          => '123abc',
                    'group_name'        => 'group name',
                    'group_description' => 'group description',
                ]
            ]
        ], $body);
    }
}