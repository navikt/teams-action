<?php declare(strict_types=1);
namespace NAV\Teams;

use PHPUnit\Framework\TestCase;

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
        $this->assertSame(
            [],
            $this->runner->run(
                [],
                $this->userObjectId,
                $this->googleSuiteProvisioningApplicationId,
                $this->googleSuiteProvisioningApplicationRoleId
            )
        );
    }
}