<?php declare(strict_types=1);
namespace NAV\Teams;

use NAV\Teams\Models\AzureAdGroup;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Middleware;

/**
 * @coversDefaultClass NAV\Teams\AzureApiClient
 */
class AzureApiClientTest extends TestCase {
    private function getMockClient(array $responses, array &$history = []) : HttpClient {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($history));

        return new HttpClient(['handler' => $handler]);
    }

    /**
     * @covers ::__construct
     */
    public function testCanConstructClient() : void {
        $clientHistory = [];
        $authClient = $this->getMockClient(
            [new Response(200, [], '{"access_token": "some secret token"}')],
            $clientHistory
        );

        new AzureApiClient($id = 'id', $secret = 'secret', $authClient);

        $this->assertCount(1, $clientHistory, 'Missing request');
        $request = $clientHistory[0]['request'];
        parse_str($request->getBody()->getContents(), $body);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame($id, $body['client_id']);
        $this->assertSame($secret, $body['client_secret']);
    }

    /**
     * @covers ::getGroupById
     */
    public function testCanGetGroupById() : void {
        $authClient = $this->getMockClient(
            [new Response(200, [], '{"access_token": "some secret token"}')]
        );
        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [new Response(200, [], '{"id": "some-id", "displayName": "some-display-name", "description": "some description"}')],
            $clientHistory
        );

        $aadGroup = (new AzureApiClient('id', 'secret', $authClient, $httpClient))->getGroupById('some-id');

        $this->assertCount(1, $clientHistory, 'Expected one request');
        $this->assertInstanceOf(AzureAdGroup::class, $aadGroup);
        $this->assertStringEndsWith('groups/some-id', (string) $clientHistory[0]['request']->getUri());
    }

    /**
     * @covers ::getGroupById
     */
    public function testReturnsNullWhenGroupByIdRequestFails() : void {
        $authClient = $this->getMockClient(
            [new Response(200, [], '{"access_token": "some secret token"}')]
        );
        $httpClient = $this->getMockClient(
            [new ClientException('group not found', new Request('GET', 'groups/some-id'))]
        );

        $this->assertNull((new AzureApiClient('id', 'secret', $authClient, $httpClient))->getGroupById('some-id'));
    }

    /**
     * @covers ::getGroupByName
     */
    public function testCanGetGroupByName() : void {
        $authClient = $this->getMockClient(
            [new Response(200, [], '{"access_token": "some secret token"}')]
        );
        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [new Response(200, [], '{"value": [{"id": "some-id", "displayName": "some-display-name", "description": "some description"}]}')],
            $clientHistory
        );

        $aadGroup = (new AzureApiClient('id', 'secret', $authClient, $httpClient))->getGroupByName('some-display-name');

        $this->assertCount(1, $clientHistory, 'Expected one request');
        $this->assertInstanceOf(AzureAdGroup::class, $aadGroup);
        $this->assertStringContainsString('filter=displayName%20eq%20%27some-display-name%27', $clientHistory[0]['request']->getUri()->getQuery());
    }

    /**
     * @covers ::getGroupByName
     */
    public function testReturnsNullWhenGroupByNameRequestFails() : void {
        $authClient = $this->getMockClient(
            [new Response(200, [], '{"access_token": "some secret token"}')]
        );
        $httpClient = $this->getMockClient(
            [new ClientException('group not found', new Request('GET', 'groups'))]
        );

        $this->assertNull((new AzureApiClient('id', 'secret', $authClient, $httpClient))->getGroupByName('some display name'));
    }

    /**
     * @covers ::getGroupByName
     */
    public function testReturnsNullWhenGroupDoesNotExist() : void {
        $authClient = $this->getMockClient(
            [new Response(200, [], '{"access_token": "some secret token"}')]
        );
        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [new Response(200, [], '{"value": []}')],
            $clientHistory
        );

        $this->assertNull((new AzureApiClient('id', 'secret', $authClient, $httpClient))->getGroupByName('some-display-name'));
        $this->assertCount(1, $clientHistory, 'Expected one request');
        $this->assertStringContainsString('filter=displayName%20eq%20%27some-display-name%27', $clientHistory[0]['request']->getUri()->getQuery());
    }

    /**
     * @covers ::createGroup
     */
    public function testCanCreateGroup() : void {
        $authClient = $this->getMockClient(
            [new Response(200, [], '{"access_token": "some secret token"}')]
        );
        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [new Response(201, [], '{"id": "some-id", "displayName": "some-display-name", "description": "some description"}')],
            $clientHistory
        );

        $aadGroup = (new AzureApiClient('id', 'secret', $authClient, $httpClient))->createGroup(
            'group name',
            'group description',
            ['Owner1@nav.no']
        );

        $this->assertInstanceOf(AzureAdGroup::class, $aadGroup);
        $this->assertCount(1, $clientHistory, 'Expected one request');

        $request = $clientHistory[0]['request'];
        $this->assertSame('POST', $request->getMethod());

        $body = json_decode($request->getBody()->getContents(), true);

        $this->assertSame('group name', $body['displayName'], 'Group name not correct');
        $this->assertStringStartsWith('group description', $body['description'], 'Group description not correct');
        $this->assertSame('group name', $body['mailNickname'], 'Mail not correct');
        $this->assertArrayHasKey('owners@odata.bind', $body, 'Missing owners list');
        $this->assertSame(['https://graph.microsoft.com/beta/users/Owner1@nav.no'], $body['owners@odata.bind'], 'Invalid owners list');
        $this->assertArrayNotHasKey('members@odata.bind', $body, 'Members list should not be present');
        $this->assertTrue($body['securityEnabled'], 'securityEnable flag not correct');
        $this->assertTrue($body['mailEnabled'], 'mailEnabled flag not correct');
    }

    /**
     * @covers ::addGroupToEnterpriseApp
     */
    public function testCanAddGroupToEnterpriseApp() : void {
        $authClient = $this->getMockClient(
            [new Response(200, [], '{"access_token": "some secret token"}')]
        );
        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [new Response()],
            $clientHistory
        );

        $aadGroup = $this->createConfiguredMock(AzureAdGroup::class, [
            'getId' => 'group-id',
            'getDisplayName' => 'group name',
        ]);

        (new AzureApiClient('id', 'secret', $authClient, $httpClient))->addGroupToEnterpriseApp($aadGroup, 'app-object-id', 'app-role-id');

        $this->assertInstanceOf(AzureAdGroup::class, $aadGroup);
        $this->assertCount(1, $clientHistory, 'Expected one request');

        $request = $clientHistory[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('servicePrincipals/app-object-id/appRoleAssignments', (string) $request->getUri());
        $this->assertSame([
            'principalId' => 'group-id',
            'appRoleId' => 'app-role-id',
            'resourceId' => 'app-object-id'
        ], json_decode($request->getBody()->getContents(), true), 'Incorrect request body');
    }

    /**
     * @covers ::getEnterpriseAppGroups
     */
    public function testCanGetEnterpriseAppGroups() : void {
        $authClient = $this->getMockClient(
            [new Response(200, [], '{"access_token": "some secret token"}')]
        );
        $clientHistory = [];
        $httpClient = $this->getMockClient(
            [
                new Response(200, [], json_encode([
                    '@odata.context' => 'context-url',
                    '@odata.nextLink' => 'next-link',
                    'value' => [['principalId' => 'first-id']],
                ])),
                new Response(200, [], json_encode([
                    '@odata.context' => 'context-url',
                    'value' => [['principalId' => 'second-id']],
                ])),
                new Response(200, [], json_encode([
                    'id' => 'first-id',
                    'displayName' => 'first-group',
                    'description' => 'first description',
                ])),
                new Response(200, [], json_encode([
                    'id' => 'second-id',
                    'displayName' => 'second-group',
                    'description' => 'second description',
                ])),
            ],
            $clientHistory
        );

        $groups = (new AzureApiClient('id', 'secret', $authClient, $httpClient))->getEnterpriseAppGroups('app-object-id');
        $this->assertCount(2, $groups);
        $this->assertCount(4, $clientHistory);
        $this->assertSame('servicePrincipals/app-object-id/appRoleAssignments?%24select=principalId&%24top=100', (string) $clientHistory[0]['request']->getUri());
        $this->assertSame('next-link', (string) $clientHistory[1]['request']->getUri());
        $this->assertSame('groups/first-id', (string) $clientHistory[2]['request']->getUri());
        $this->assertSame('groups/second-id', (string) $clientHistory[3]['request']->getUri());
    }
}