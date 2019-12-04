<?php declare(strict_types=1);
namespace NAV\Teams;

use GuzzleHttp\Exception\ClientException;
use NAV\Teams\Exceptions\InvalidArgumentException;
use NAV\Teams\Models\AzureAdGroup;
use NAV\Teams\Models\GitHubTeam;
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

    public function setUp() : void {
        $this->azureApiClient = $this->createMock(AzureApiClient::class);
        $this->githubApiClient = $this->createMock(GitHubApiClient::class);
        $this->naisDeploymentApiClient = $this->createMock(NaisDeploymentApiClient::class);

        $this->runner = new Runner(
            $this->azureApiClient,
            $this->githubApiClient,
            $this->naisDeploymentApiClient
        );
    }

    /**
     * @covers ::run
     */
    public function testEmptyTeamList() : void {
        $this->assertSame([], $this->runRunner([]));
    }

    /**
     * @covers ::run
     */
    public function testHandlesAzureAdGroupAlreadyExists() : void {
        $this->azureApiClient
            ->expects($this->once())
            ->method('getGroupByName')
            ->with('team-name')
            ->willReturn($this->createConfiguredMock(AzureAdGroup::class, ['getId' => 'group-id']));

        $this->assertArrayHasKey('team-name', $result = $this->runRunner([['name' => 'team-name', 'description' => 'team description']]));
        $teamResult = $result['team-name'];
        $this->assertSame('team-name', $teamResult->getTeamName());
        $this->assertSame('Group already exists in Azure AD with ID "group-id", skipping...', $teamResult->getMessage());
        $this->assertTrue($teamResult->skipped());
        $this->assertFalse($teamResult->added());
        $this->assertFalse($teamResult->failed());
    }

    /**
     * @covers ::run
     */
    public function testHandlesGitHubTeamAlreadyExists() : void {
        $this->githubApiClient
            ->expects($this->once())
            ->method('getTeam')
            ->with('team-name')
            ->willReturn($this->createConfiguredMock(GitHubTeam::class, ['getId' => 123]));

        $this->assertArrayHasKey('team-name', $result = $this->runRunner([['name' => 'team-name', 'description' => 'team description']]));
        $teamResult = $result['team-name'];
        $this->assertSame('team-name', $teamResult->getTeamName());
        $this->assertSame('Team "team-name" (ID: 123) already exists on GitHub, skipping...', $teamResult->getMessage());
        $this->assertTrue($teamResult->skipped());
        $this->assertFalse($teamResult->added());
        $this->assertFalse($teamResult->failed());
    }

    /**
     * @covers ::run
     */
    public function testHandlesAzureAdGroupCreationFailure() : void {
        $this->azureApiClient
            ->expects($this->once())
            ->method('createGroup')
            ->with('team-name', 'team description', [$this->userObjectId], [$this->userObjectId])
            ->willThrowException($this->getClientException('error message'));

        $this->assertArrayHasKey('team-name', $result = $this->runRunner([['name' => 'team-name', 'description' => 'team description']]));
        $teamResult = $result['team-name'];
        $this->assertSame('team-name', $teamResult->getTeamName());
        $this->assertSame('Unable to create Azure AD group: "team-name". Error message: error message', $teamResult->getMessage());
        $this->assertTrue($teamResult->failed());
        $this->assertFalse($teamResult->added());
        $this->assertFalse($teamResult->skipped());
    }

    /**
     * @covers ::run
     */
    public function testHandlesAzureAdGroupNotAddedToContainerApp() : void {
        $aadGroup = $this->createMock(AzureAdGroup::class);

        $this->azureApiClient
            ->expects($this->once())
            ->method('createGroup')
            ->with('team-name', 'team description', [$this->userObjectId], [$this->userObjectId])
            ->willReturn($aadGroup);
        $this->azureApiClient
            ->expects($this->once())
            ->method('addGroupToEnterpriseApp')
            ->with($aadGroup, $this->containerApplicationId, $this->containerApplicationRoleId)
            ->willThrowException($this->getClientException('error message'));

        $this->assertArrayHasKey('team-name', $result = $this->runRunner([['name' => 'team-name', 'description' => 'team description']]));
        $teamResult = $result['team-name'];
        $this->assertSame('team-name', $teamResult->getTeamName());
        $this->assertSame('Unable to add the Azure AD group to the teams management application', $teamResult->getMessage());
        $this->assertTrue($teamResult->failed());
        $this->assertFalse($teamResult->added());
        $this->assertFalse($teamResult->skipped());
    }

    /**
     * @covers ::run
     */
    public function testHandlesAzureAdGroupNotAddedToGoogleApp() : void {
        $aadGroup = $this->createMock(AzureAdGroup::class);

        $this->azureApiClient
            ->expects($this->once())
            ->method('createGroup')
            ->with('team-name', 'team description', [$this->userObjectId], [$this->userObjectId])
            ->willReturn($aadGroup);
        $this->azureApiClient
            ->expects($this->exactly(2))
            ->method('addGroupToEnterpriseApp')
            ->withConsecutive(
                [$aadGroup, $this->containerApplicationId, $this->containerApplicationRoleId],
                [$aadGroup, $this->googleSuiteProvisioningApplicationId, $this->googleSuiteProvisioningApplicationRoleId]
            )
            ->will($this->onConsecutiveCalls(
                null,
                $this->throwException($this->getClientException('error message'))
            ));

        $this->assertArrayHasKey('team-name', $result = $this->runRunner([['name' => 'team-name', 'description' => 'team description']]));
        $teamResult = $result['team-name'];
        $this->assertSame('team-name', $teamResult->getTeamName());
        $this->assertSame('Unable to add the Azure AD group to the Google Suite Provisioning application', $teamResult->getMessage());
        $this->assertTrue($teamResult->failed());
        $this->assertFalse($teamResult->added());
        $this->assertFalse($teamResult->skipped());
    }

    /**
     * @covers ::run
     */
    public function testHandlesGitHubTeamCreationFailure() : void {
        $aadGroup = $this->createMock(AzureAdGroup::class);

        $this->azureApiClient
            ->expects($this->once())
            ->method('createGroup')
            ->with('team-name', 'team description', [$this->userObjectId], [$this->userObjectId])
            ->willReturn($aadGroup);

        $this->githubApiClient
            ->expects($this->once())
            ->method('createTeam')
            ->with('team-name', 'team description')
            ->willThrowException($this->getClientException('error message'));

        $this->assertArrayHasKey('team-name', $result = $this->runRunner([['name' => 'team-name', 'description' => 'team description']]));
        $teamResult = $result['team-name'];
        $this->assertSame('team-name', $teamResult->getTeamName());
        $this->assertSame('Unable to create GitHub team "team-name". Error message: error message', $teamResult->getMessage());
        $this->assertTrue($teamResult->failed());
        $this->assertFalse($teamResult->added());
        $this->assertFalse($teamResult->skipped());
    }

    /**
     * @covers ::run
     */
    public function testHandlesGitHubTeamSyncFailure() : void {
        $aadGroup = $this->createMock(AzureAdGroup::class);

        $this->azureApiClient
            ->expects($this->once())
            ->method('createGroup')
            ->with('team-name', 'team description', [$this->userObjectId], [$this->userObjectId])
            ->willReturn($aadGroup);

        $githubTeam = $this->createMock(GitHubTeam::class);

        $this->githubApiClient
            ->expects($this->once())
            ->method('createTeam')
            ->with('team-name', 'team description')
            ->willReturn($githubTeam);
        $this->githubApiClient
            ->expects($this->once())
            ->method('syncTeamAndGroup')
            ->with($githubTeam, $aadGroup)
            ->willThrowException($this->getClientException('error message'));

        $this->assertArrayHasKey('team-name', $result = $this->runRunner([['name' => 'team-name', 'description' => 'team description']]));
        $teamResult = $result['team-name'];
        $this->assertSame('team-name', $teamResult->getTeamName());
        $this->assertSame('Unable to sync GitHub team and Azure AD group. Error message: error message', $teamResult->getMessage());
        $this->assertTrue($teamResult->failed());
        $this->assertFalse($teamResult->added());
        $this->assertFalse($teamResult->skipped());
    }

    /**
     * @covers ::run
     */
    public function testHandlesNaisDeploymentKeyProvisioningFailure() : void {
        $aadGroup = $this->createMock(AzureAdGroup::class);

        $this->azureApiClient
            ->expects($this->once())
            ->method('createGroup')
            ->with('team-name', 'team description', [$this->userObjectId], [$this->userObjectId])
            ->willReturn($aadGroup);

        $githubTeam = $this->createMock(GitHubTeam::class);

        $this->githubApiClient
            ->expects($this->once())
            ->method('createTeam')
            ->with('team-name', 'team description')
            ->willReturn($githubTeam);

        $this->naisDeploymentApiClient
            ->expects($this->once())
            ->method('provisionTeamKey')
            ->with('team-name')
            ->willThrowException($this->getClientException('error message'));

        $this->assertArrayHasKey('team-name', $result = $this->runRunner([['name' => 'team-name', 'description' => 'team description']]));
        $teamResult = $result['team-name'];
        $this->assertSame('team-name', $teamResult->getTeamName());
        $this->assertSame('Unable to create Nais deployment key. Error message: error message', $teamResult->getMessage());
        $this->assertTrue($teamResult->failed());
        $this->assertFalse($teamResult->added());
        $this->assertFalse($teamResult->skipped());
    }

    /**
     * @covers ::run
     * @covers ::__construct
     */
    public function testHandlesTeamCreation() : void {
        $aadGroup = $this->createMock(AzureAdGroup::class);

        $this->azureApiClient
            ->expects($this->once())
            ->method('createGroup')
            ->with('team-name', 'team description', [$this->userObjectId], [$this->userObjectId])
            ->willReturn($aadGroup);

        $githubTeam = $this->createMock(GitHubTeam::class);

        $this->githubApiClient
            ->expects($this->once())
            ->method('createTeam')
            ->with('team-name', 'team description')
            ->willReturn($githubTeam);

        $this->assertArrayHasKey('team-name', $result = $this->runRunner([['name' => 'team-name', 'description' => 'team description']]));
        $teamResult = $result['team-name'];
        $this->assertSame('team-name', $teamResult->getTeamName());
        $this->assertTrue($teamResult->added());
        $this->assertFalse($teamResult->failed());
        $this->assertFalse($teamResult->skipped());
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
     * Execute the runner
     *
     * @param array $teams
     * @return array
     */
    private function runRunner(array $teams) : array {
        return $this->runner->run(
            $teams,
            $this->userObjectId,
            $this->googleSuiteProvisioningApplicationId,
            $this->googleSuiteProvisioningApplicationRoleId,
            $this->containerApplicationId,
            $this->containerApplicationRoleId
        );
    }

    /**
     * Get a Guzzle client exception
     *
     * @param string $message The error message
     */
    private function getClientException(string $message) : ClientException {
        return new ClientException($message, $this->createMock(RequestInterface::class));
    }
}