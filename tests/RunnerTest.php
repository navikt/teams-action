<?php declare(strict_types=1);
namespace NAVIT\Teams;

use NAVIT\{
    AzureAd\ApiClient as AzureAdApiClient,
    GitHub\ApiClient as GitHubApiClient,
    Teams\Runner\Output,
    Teams\Runner\Result,
};
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\{
    MockObject\MockObject,
    TestCase,
};
use Psr\Http\Message\RequestInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Namespaced version of sleep that does not sleep
 *
 * @param int $seconds
 * @return int
 */
function sleep(int $seconds) : int {
    echo sprintf('sleep %d', $seconds);
    return 0;
}

/**
 * @coversDefaultClass NAVIT\Teams\Runner
 */
class RunnerTest extends TestCase {
    /** @var AzureAdApiClient&MockObject */
    private AzureAdApiClient $azureAdApiClient;

    /** @var GitHubApiClient&MockObject */
    private GitHubApiClient $githubApiClient;

    /** @var NaisDeploymentApiClient&MockObject */
    private NaisDeploymentApiClient $naisDeploymentApiClient;

    /** @var Output&MockObject */
    private Output $output;

    private string $userObjectId = 'user-object-id';
    private string $containerApplicationId = 'container-application-id';
    private string $containerApplicationRoleId = 'container-application-role-id';
    private Runner $runner;

    public function setUp() : void {
        $this->azureAdApiClient = $this->createMock(AzureAdApiClient::class);
        $this->githubApiClient = $this->createMock(GitHubApiClient::class);
        $this->naisDeploymentApiClient = $this->createMock(NaisDeploymentApiClient::class);
        $this->output = $this->createMock(Output::class);

        $this->runner = new Runner(
            $this->azureAdApiClient,
            $this->githubApiClient,
            $this->naisDeploymentApiClient,
            $this->output,
        );
    }

    /**
     * @return array<string,array{0:array{description?:string,name?:string},1:string}>
     */
    public function getInvalidTeams() : array {
        return [
            'missing name' => [
                ['description' => 'team description'],
                'Missing team name: description:',
            ],
            'missing description' => [
                ['name' => 'team-name'],
                'Missing team description:',
            ],
            'invalid name' => [
                ['name' => 'æøå', 'description' => 'team description'],
                'Invalid team name: æøå',
            ],
        ];
    }

    /**
     * @dataProvider getInvalidTeams
     * @covers ::run
     * @covers ::validateTeams
     * @param array{description?:string,name?:string} $team
     */
    public function testThrowsExceptionOnInvalidTeamsArray(array $team, string $expectedErrorMessage) : void {
        $this->expectExceptionObject(new InvalidArgumentException($expectedErrorMessage));
        $this->runRunner([$team]);
    }

    /**
     * @covers ::run
     * @covers ::validateTeams
     */
    public function testThrowsExceptionWhenFailingToFetchManagedTeams() : void {
        $this->expectExceptionObject(new RuntimeException('Unable to fetch managed teams, aborting...'));
        $this->azureAdApiClient
            ->expects($this->once())
            ->method('getEnterpriseAppGroups')
            ->with($this->containerApplicationId)
            ->willReturn([]);

        $this->runRunner([['name' => 'team', 'description' => 'some description']]);
    }

    /**
     * @covers ::run
     * @covers ::validateTeams
     */
    public function testWillSkipNonManagedGroups() : void {
        $this->azureAdApiClient
            ->expects($this->once())
            ->method('getEnterpriseAppGroups')
            ->with($this->containerApplicationId)
            ->willReturn([
                [
                    'id'           => 'managed-group-id',
                    'mailNickname' => 'managed-group-mail',
                ],
            ]);

        $this->azureAdApiClient
            ->expects($this->once())
            ->method('getGroupByMailNickname')
            ->with('non-managed-group-name')
            ->willReturn([
                'id'           => 'non-managed-group-id',
                'mailNickname' => 'non-managed-group-name',
            ]);

        $this->output
            ->expects($this->once())
            ->method('debug')
            ->with('non-managed-group-name', 'A non-managed group with this name already exists in Azure AD with ID "non-managed-group-id", skipping...');

        $this->runRunner([['name' => 'non-managed-group-name', 'description' => 'non-managed-group-description']]);
    }

    /**
     * @covers ::__construct
     * @covers ::run
     */
    public function testSupportsRunningWithMultipleTeams() : void {
        $managedGroup1 = $this->getAadGroup('managed-team-1-id', 'managed-team-1-name', 'managed-team-1-description', 'mail1');
        $managedGroup2 = $this->getAadGroup('managed-team-2-id', 'managed-team-2-name', 'managed-team-2-description', 'mail2');

        $group1 = $this->getAadGroup('managed-team-1-id', 'managed-team-1-name', 'managed-team-1-description', 'mail1');
        $group2 = $this->getAadGroup('managed-team-2-id', 'managed-team-2-name', 'managed-team-2-description', 'mail2');
        $group3 = $this->getAadGroup('non-managed-team-id', 'non-managed-team-name', 'non-managed-team-description', 'somemail');

        $newGroup = $this->getAadGroup('new-team-id', 'new-team-name', 'new-team-description', 'new');

        $this->azureAdApiClient
            ->expects($this->once())
            ->method('getEnterpriseAppGroups')
            ->with($this->containerApplicationId)
            ->willReturn([
                $managedGroup1,
                $managedGroup2,
            ]);

        $this->azureAdApiClient
            ->expects($this->exactly(4))
            ->method('getGroupByMailNickname')
            ->withConsecutive(
                ['managed-team-1-name'],
                ['managed-team-2-name'],
                ['non-managed-team-name'],
                ['new-team-name'],
            )
            ->willReturnOnConsecutiveCalls(
                $group1,
                $group2,
                $group3,
                null, // group not found
            );

        $this->azureAdApiClient
            ->expects($this->exactly(2))
            ->method('addUserToGroup')
            ->withConsecutive(
                [$this->userObjectId, 'extra-group-1'],
                [$this->userObjectId, 'extra-group-2'],
            );

        $this->azureAdApiClient
            ->expects($this->once())
            ->method('setGroupDescription')
            ->with('managed-team-2-id', 'managed-team-2-new-description');

        $this->azureAdApiClient
            ->expects($this->once())
            ->method('createGroup')
            ->with('new-team-name', 'new-team-description', [$this->userObjectId], [$this->userObjectId])
            ->willReturn($newGroup);

        $this->azureAdApiClient
            ->expects($this->once())
            ->method('addGroupToEnterpriseApp')
            ->with(
                'new-team-id',
                $this->containerApplicationId,
                $this->containerApplicationRoleId,
            );

        $githubTeam1 = $this->getGitHubTeam(123, 'managed-team-1-name', 'managed-team-1-name');
        $newGitHubTeam1 = $this->getGitHubTeam(456, 'managed-team-2-name', 'managed-team-2-name');
        $newGitHubTeam2 = $this->getGitHubTeam(789, 'new-team-name', 'new-team-name');

        $this->githubApiClient
            ->expects($this->exactly(3))
            ->method('getTeam')
            ->withConsecutive(
                ['managed-team-1-name'],
                ['managed-team-2-name'],
                ['new-team-name'],
            )
            ->willReturnOnConsecutiveCalls(
                $githubTeam1,
                null, // team not found
                null, // team not found
            );

        $this->githubApiClient
            ->expects($this->exactly(2))
            ->method('createTeam')
            ->withConsecutive(
                ['managed-team-2-name', 'managed-team-2-new-description'],
                ['new-team-name', 'new-team-description'],
            )
            ->willReturnOnConsecutiveCalls(
                $newGitHubTeam1,
                $newGitHubTeam2,
            );

        $this->githubApiClient
            ->expects($this->exactly(2))
            ->method('syncTeamAndGroup')
            ->withConsecutive(
                ['managed-team-2-name', 'managed-team-2-id', 'managed-team-2-name', 'managed-team-2-description'],
                ['new-team-name', 'new-team-id', 'new-team-name', 'new-team-description'],
            );

        $this->naisDeploymentApiClient
            ->expects($this->exactly(3))
            ->method('provisionTeamKey')
            ->withConsecutive(
                ['managed-team-1-name'],
                ['managed-team-2-name'],
                ['new-team-name'],
            );

        $result = $this->runRunner([
            [
                'name'        => 'managed-team-1-name',
                'description' => 'managed-team-1-description',
            ],
            [
                'name'        => 'managed-team-2-name',
                'description' => 'managed-team-2-new-description',
            ],
            [
                'name'        => 'non-managed-team-name',
                'description' => 'non-managed-team-description',
            ],
            [
                'name'        => 'new-team-name',
                'description' => 'new-team-description',
            ],
        ], [
            'extra-group-1',
            'extra-group-2',
        ]);

        $this->assertSame([
            [
                'teamName' => 'managed-team-1-name',
                'groupId'  => 'managed-team-1-id',
            ],
            [
                'teamName' => 'managed-team-2-name',
                'groupId'  => 'managed-team-2-id',
            ],
            [
                'teamName' => 'new-team-name',
                'groupId'  => 'new-team-id',
            ],
        ], json_decode((string) json_encode($result), true));
    }

    /**
     * @covers ::run
     * @covers ::provisionTeamKey
     */
    public function testWillRetryNaisDeploymentProvisioning() : void {
        $this->azureAdApiClient
            ->expects($this->once())
            ->method('getEnterpriseAppGroups')
            ->willReturn([
                $this->getAadGroup('id', 'name', 'description', 'mail')
            ]);

        $this->azureAdApiClient
            ->expects($this->once())
            ->method('getGroupByMailNickname')
            ->with('newname')
            ->willReturn(null);

        $this->azureAdApiClient
            ->expects($this->once())
            ->method('createGroup')
            ->with('newname', 'newdescription', [$this->userObjectId], [$this->userObjectId])
            ->willReturn([
                'id'           => 'newid',
                'description'  => 'newdescription',
                'displayName'  => 'newname',
                'mailNickname' => 'newname@example.com',
            ]);

        $this->githubApiClient
            ->expects($this->once())
            ->method('createTeam')
            ->with('newname', 'newdescription')
            ->willReturn([
                'id'   => 123,
                'name' => 'newname',
                'slug' => 'newname',
            ]);

        $this->output
            ->method('debug')
            ->withConsecutive(
                ['newname', 'Group does not exist in Azure AD, creating...'],
                ['newname', 'Group has been created in Azure AD, ID: "newid"'],
                ['newname', 'Team does not exist on GitHub, creating...'],
                ['newname', 'Team has been created on GitHub, ID: 123'],
                ['newname', 'Enable sync between Azure AD group and GitHub team...'],
                ['newname', 'Unable to provision a NAIS deployment key at the moment, waiting 3 second(s)'],
                ['newname', 'Unable to provision a NAIS deployment key at the moment, waiting 7 second(s)'],
                ['newname', 'Unable to provision a NAIS deployment key at the moment, waiting 15 second(s)'],
                ['newname', 'Unable to provision a NAIS deployment key at the moment, waiting 31 second(s)'],
            );

        $this->output
            ->expects($this->once())
            ->method('failure')
            ->with('newname', 'Unable to provision NAIS deployment key, error message: some failure');

        $this->naisDeploymentApiClient
            ->method('provisionTeamKey')
            ->with('newname')
            ->willThrowException(new ClientException(
                'some failure',
                $this->createMock(RequestInterface::class),
                $this->createMock(ResponseInterface::class),
            ));

        $this->expectOutputString('sleep 3sleep 7sleep 15sleep 31');

        $this->runRunner([
            [
                'name'        => 'newname',
                'description' => 'newdescription',
            ],
        ]);
    }

    /**
     * Execute the runner
     *
     * @param array<array{name?:string,description?:string}> $teams
     * @param string[] $extraGroups Extra groups to add the user to
     * @return Result
     */
    private function runRunner(array $teams, array $extraGroups = []) : Result {
        return $this->runner->run(
            $teams,
            $this->userObjectId,
            $this->containerApplicationId,
            $this->containerApplicationRoleId,
            $extraGroups,
        );
    }

    /**
     * Get an AAD group
     *
     * @param string $id
     * @param string $displayName
     * @param string $description
     * @param string $mailNickname
     * @return array{id:string,displayName:string,description:string,mailNickname:string}
     */
    private function getAadGroup(string $id, string $displayName, string $description, string $mailNickname) : array {
        return [
            'id'           => $id,
            'displayName'  => $displayName,
            'description'  => $description,
            'mailNickname' => $mailNickname,
        ];
    }

    /**
     * Get a GitHub team
     *
     * @param int $id
     * @param string $name
     * @param string $slug
     * @return array{id:int,name:string,slug:string}
     */
    private function getGitHubTeam(int $id, string $name, string $slug) : array {
        return [
            'id'   => $id,
            'name' => $name,
            'slug' => $slug,
        ];
    }
}