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