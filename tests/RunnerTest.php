<?php declare(strict_types=1);
namespace NAV\Teams;

use NAV\Teams\Exceptions\InvalidArgumentException;
use NAV\Teams\Exceptions\RuntimeException;
use NAV\Teams\Models\AzureAdGroup;
use NAV\Teams\Models\GitHubTeam;
use NAV\Teams\Runner\Output;
use NAV\Teams\Runner\Result;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @coversDefaultClass NAV\Teams\Runner
 */
class RunnerTest extends TestCase {
    private $azureApiClient;
    private $githubApiClient;
    private $naisDeploymentApiClient;
    private $userObjectId = 'user-object-id';
    private $googleSuiteProvisioningApplicationId = 'google-suite-application-id';
    private $googleSuiteProvisioningApplicationRoleId = 'google-suite-application-role-id';
    private $containerApplicationId = 'container-application-id';
    private $containerApplicationRoleId = 'conatiner-application-role-id';
    private $output;
    private $runner;

    public function setUp() : void {
        $this->azureApiClient = $this->createMock(AzureApiClient::class);
        $this->githubApiClient = $this->createMock(GitHubApiClient::class);
        $this->naisDeploymentApiClient = $this->createMock(NaisDeploymentApiClient::class);
        $this->output = $this->createMock(Output::class);

        $this->runner = new Runner(
            $this->azureApiClient,
            $this->githubApiClient,
            $this->naisDeploymentApiClient,
            $this->output
        );
    }

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
        $this->azureApiClient
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
        $this->azureApiClient
            ->expects($this->once())
            ->method('getEnterpriseAppGroups')
            ->with($this->containerApplicationId)
            ->willReturn([$this->createConfiguredMock(AzureAdGroup::class, [
                'getDisplayName' => 'managed-group-name',
                'getId' => 'managed-group-id',
            ])]);

        $this->azureApiClient
            ->expects($this->once())
            ->method('getGroupByName')
            ->with('non-managed-group-name')
            ->willReturn($this->createConfiguredMock(AzureAdGroup::class, [
                'getDisplayName' => 'non-managed-group-name',
                'getId' => 'non-managed-group-id',
            ]));

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
        $managedGroup1 = new AzureAdGroup('managed-team-1-id', 'managed-team-1-name', 'managed-team-1-description', 'mail1@nav.no');
        $managedGroup2 = new AzureAdGroup('managed-team-2-id', 'managed-team-2-name', 'managed-team-2-description', 'mail2@nav.no');

        $group1 = new AzureAdGroup('managed-team-1-id', 'managed-team-1-name', 'managed-team-1-description', 'mail@nav.no');
        $group2 = new AzureAdGroup('managed-team-2-id', 'managed-team-2-name', 'managed-team-2-description', 'mail2@nav.no');
        $group3 = new AzureAdGroup('non-managed-team-id', 'non-managed-team-name', 'non-managed-team-description', 'somemail@nav.no');

        $newGroup = new AzureAdGroup('new-team-id', 'new-team-name', 'new-team-description', 'new@nav.no');

        $this->azureApiClient
            ->expects($this->once())
            ->method('getEnterpriseAppGroups')
            ->with($this->containerApplicationId)
            ->willReturn([
                $managedGroup1,
                $managedGroup2,
            ]);

        $this->azureApiClient
            ->expects($this->exactly(4))
            ->method('getGroupByName')
            ->withConsecutive(
                ['managed-team-1-name'],
                ['managed-team-2-name'],
                ['non-managed-team-name'],
                ['new-team-name']
            )
            ->willReturnOnConsecutiveCalls(
                $group1,
                $group2,
                $group3,
                null // group not found
            );

        $this->azureApiClient
            ->expects($this->once())
            ->method('setGroupDescription')
            ->with('managed-team-2-id', 'managed-team-2-new-description');

        $this->azureApiClient
            ->expects($this->once())
            ->method('createGroup')
            ->with('new-team-name', 'new-team-description', [$this->userObjectId], [$this->userObjectId])
            ->willReturn($newGroup);

        $this->azureApiClient
            ->expects($this->exactly(4))
            ->method('addGroupToEnterpriseApp')
            ->withConsecutive([
                $group1,
                $this->googleSuiteProvisioningApplicationId,
                $this->googleSuiteProvisioningApplicationRoleId,
            ], [
                $group2,
                $this->googleSuiteProvisioningApplicationId,
                $this->googleSuiteProvisioningApplicationRoleId,
            ], [
                $newGroup,
                $this->containerApplicationId,
                $this->containerApplicationRoleId,
            ], [
                $newGroup,
                $this->googleSuiteProvisioningApplicationId,
                $this->googleSuiteProvisioningApplicationRoleId,
            ]);

        $githubTeam1 = new GitHubTeam(123, 'managed-team-1-name');
        $newGitHubTeam1 = new GitHubTeam(456, 'managed-team-2-name');
        $newGitHubTeam2 = new GitHubTeam(789, 'new-team-name');

        $this->githubApiClient
            ->expects($this->exactly(3))
            ->method('getTeam')
            ->withConsecutive(
                ['managed-team-1-name'],
                ['managed-team-2-name'],
                ['new-team-name']
            )
            ->willReturnOnConsecutiveCalls(
                $githubTeam1,
                null, // team not found
                null  // team not found
            );

        $this->githubApiClient
            ->expects($this->exactly(2))
            ->method('createTeam')
            ->withConsecutive(
                ['managed-team-2-name', 'managed-team-2-new-description'],
                ['new-team-name', 'new-team-description']
            )
            ->willReturnOnConsecutiveCalls(
                $newGitHubTeam1,
                $newGitHubTeam2
            );

        $this->githubApiClient
            ->expects($this->exactly(2))
            ->method('syncTeamAndGroup')
            ->withConsecutive(
                [$newGitHubTeam1, $managedGroup2],
                [$newGitHubTeam2, $newGroup]
            );

        $this->naisDeploymentApiClient
            ->expects($this->exactly(3))
            ->method('provisionTeamKey')
            ->withConsecutive(
                ['managed-team-1-name'],
                ['managed-team-2-name'],
                ['new-team-name']
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
        ], json_decode(json_encode($result), true));
    }

    /**
     * Execute the runner
     *
     * @param array $teams
     * @return Result
     */
    private function runRunner(array $teams) : Result {
        return $this->runner->run(
            $teams,
            $this->userObjectId,
            $this->googleSuiteProvisioningApplicationId,
            $this->googleSuiteProvisioningApplicationRoleId,
            $this->containerApplicationId,
            $this->containerApplicationRoleId
        );
    }
}