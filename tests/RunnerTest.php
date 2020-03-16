<?php declare(strict_types=1);
namespace NAVIT\Teams;

use NAVIT\AzureAd\ApiClient as AzureAdApiClient;
use NAVIT\AzureAd\Models\Group as AzureAdGroup;
use NAVIT\GitHub\ApiClient as GitHubApiClient;
use NAVIT\GitHub\Models\Team as GitHubTeam;
use NAVIT\Teams\Runner\Output;
use NAVIT\Teams\Runner\Result;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use RuntimeException;

/**
 * @coversDefaultClass NAVIT\Teams\Runner
 */
class RunnerTest extends TestCase {
    private $azureAdApiClient;
    private $githubApiClient;
    private $naisDeploymentApiClient;
    private $userObjectId = 'user-object-id';
    private $containerApplicationId = 'container-application-id';
    private $containerApplicationRoleId = 'conatiner-application-role-id';
    private $output;
    private $runner;

    public function setUp() : void {
        /** @var AzureAdApiClient */
        $this->azureAdApiClient = $this->createMock(AzureAdApiClient::class);

        /** @var GitHubApiClient */
        $this->githubApiClient = $this->createMock(GitHubApiClient::class);

        /** @var NaisDeploymentApiClient */
        $this->naisDeploymentApiClient = $this->createMock(NaisDeploymentApiClient::class);

        /** @var Output */
        $this->output = $this->createMock(Output::class);

        $this->runner = new Runner(
            $this->azureAdApiClient,
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
            ->willReturn([$this->createConfiguredMock(AzureAdGroup::class, [
                'getMailNickname' => 'managed-group-name',
                'getId' => 'managed-group-id',
            ])]);

        $this->azureAdApiClient
            ->expects($this->once())
            ->method('getGroupByMailNickname')
            ->with('non-managed-group-name')
            ->willReturn($this->createConfiguredMock(AzureAdGroup::class, [
                'getMailNickname' => 'non-managed-group-name',
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
        $managedGroup1 = new AzureAdGroup('managed-team-1-id', 'managed-team-1-name', 'managed-team-1-description', 'mail1');
        $managedGroup2 = new AzureAdGroup('managed-team-2-id', 'managed-team-2-name', 'managed-team-2-description', 'mail2');

        $group1 = new AzureAdGroup('managed-team-1-id', 'managed-team-1-name', 'managed-team-1-description', 'mail1');
        $group2 = new AzureAdGroup('managed-team-2-id', 'managed-team-2-name', 'managed-team-2-description', 'mail2');
        $group3 = new AzureAdGroup('non-managed-team-id', 'non-managed-team-name', 'non-managed-team-description', 'somemail');

        $newGroup = new AzureAdGroup('new-team-id', 'new-team-name', 'new-team-description', 'new');

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
                ['new-team-name']
            )
            ->willReturnOnConsecutiveCalls(
                $group1,
                $group2,
                $group3,
                null // group not found
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
                $this->containerApplicationRoleId
            );

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
                [456, 'managed-team-2-id', 'managed-team-2-name', 'managed-team-2-description'],
                [789, 'new-team-id', 'new-team-name', 'new-team-description']
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
            $this->containerApplicationId,
            $this->containerApplicationRoleId
        );
    }
}