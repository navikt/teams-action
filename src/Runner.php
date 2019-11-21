<?php declare(strict_types=1);
namespace NAV\Teams;

use NAV\Teams\Runner\TeamResult;
use GuzzleHttp\Exception\ClientException;

class Runner {
    /**
     * @var AzureApiClient
     */
    private $azureApiClient;

    /**
     * @var GitHubApiClient
     */
    private $githubApiClient;

    /**
     * @var NaisDeploymentApiClient
     */
    private $naisDeploymentApiClient;

    /**
     * Class constructor
     *
     * @param AzureApiClient $aadClient
     * @param GitHubApiClient $githubClient
     * @param NaisDeplymentApiClient $naisClient
     */
    public function __construct(
        AzureApiClient $azureApiClient,
        GitHubApiClient $githubApiClient,
        NaisDeploymentApiClient $naisDeploymentApiClient
    ) {
        $this->azureApiClient = $azureApiClient;
        $this->githubApiClient = $githubApiClient;
        $this->naisDeploymentApiClient = $naisDeploymentApiClient;
    }

    /**
     * Run the action
     *
     * @param array $teams List of teams
     * @param string $userObjectId ID The Azure AD user object ID that initiated the run
     * @param string $googleSuiteProvisioningApplicationId
     * @param string $googleSuiteProvisioningApplicationRoleId
     * @return TeamResult[]
     */
    public function run(
        array $teams,
        string $userObjectId,
        string $googleSuiteProvisioningApplicationId,
        string $googleSuiteProvisioningApplicationRoleId
    ) : array {
        $result = [];

        foreach ($teams as $teamName) {
            $aadGroup = $this->azureApiClient->getGroupByName($teamName);

            if (null !== $aadGroup) {
                $result[$teamName] = new TeamResult(
                    $teamName,
                    sprintf('Group already exists in Azure AD with ID "%s", skipping...', $aadGroup->getId()),
                    TeamResult::TEAM_SKIPPED
                );
                continue;
            }

            $githubTeam = $this->githubApiClient->getTeam($teamName);

            if (null !== $githubTeam) {
                $result[$teamName] = new TeamResult(
                    $teamName,
                    sprintf('Team "%s" (ID: %d) already exists on GitHub, skipping...', $teamName, $githubTeam->getId()),
                    TeamResult::TEAM_SKIPPED
                );
                continue;
            }

            try {
                $aadGroup = $this->azureApiClient->createGroup($teamName, [$userObjectId], [$userObjectId]);
            } catch (ClientException $e) {
                $result[$teamName] = new TeamResult(
                    $teamName,
                    sprintf('Unable to create Azure AD group: "%s". Error message: %s', $teamName, $e->getMessage()),
                    TeamResult::TEAM_FAILURE
                );
                continue;
            }

            try {
                $this->azureApiClient->addGroupToEnterpriseApp($aadGroup, $googleSuiteProvisioningApplicationId, $googleSuiteProvisioningApplicationRoleId);
            } catch (ClientException $e) {
                $result[$teamName] = new TeamResult(
                    $teamName,
                    'Unable to add the Azure AD group to the Google Suite Provisioning application',
                    TeamResult::TEAM_FAILURE
                );
                continue;
            }

            try {
                $githubTeam = $this->githubApiClient->createTeam($teamName, $aadGroup);
            } catch (ClientException $e) {
                $result[$teamName] = new TeamResult(
                    $teamName,
                    sprintf('Unable to create GitHub team "%s". Error message: %s', $teamName, $e->getMessage()),
                    TeamResult::TEAM_FAILURE
                );
                continue;
            }

            try {
                $this->githubApiClient->syncTeamAndGroup($githubTeam, $aadGroup);
            } catch (ClientException $e) {
                $result[$teamName] = new TeamResult(
                    $teamName,
                    sprintf('Unable to sync GitHub team and Azure AD group. Error message: %s', $e->getMessage()),
                    TeamResult::TEAM_FAILURE
                );
                continue;
            }

            try {
                $this->naisDeploymentApiClient->provisionTeamKey($teamName);
            } catch (ClientException $e) {
                $result[$teamName] = new TeamResult(
                    $teamName,
                    sprintf('Unable to create Nais deployment key. Error message: %s', $e->getMessage()),
                    TeamResult::TEAM_FAILURE
                );
                continue;
            }

            $result[$teamName] = new TeamResult($teamName, 'Team added', TeamResult::TEAM_ADDED);
        }

        return $result;
    }
}