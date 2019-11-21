<?php declare(strict_types=1);
namespace NAV\Teams;

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
     * @return array Each entry in the array is an object with the team name as the key, and the following keys as value:
     *               - <string> message: A textual description
     *               - <bool> skipped: Whether or not the team was skipped (defaults to false)
     *               - <bool> failure: Whether or not an error occurred when trying to add the team / group (defaults to false)
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
                $result[$teamName] = [
                    'message' => sprintf('Group "%s" (ID: %s) already exists in Azure AD, skipping...', $teamName, $aadGroup->getId()),
                    'skipped' => true,
                ];
                continue;
            }

            $githubTeam = $this->githubApiClient->getTeam($teamName);

            if (null !== $githubTeam) {
                $result[$teamName] = [
                    'message' => sprintf('Team "%s" (ID: %d) already exists on GitHub, skipping...', $teamName, $githubTeam->getId()),
                    'skipped' => true,
                ];
                continue;
            }

            try {
                $aadGroup = $this->azureApiClient->createGroup($teamName, [$userObjectId], [$userObjectId]);
            } catch (ClientException $e) {
                $result[$teamName] = [
                    'message' => sprintf('Unable to create Azure AD group: "%s". Error message: %s', $teamName, $e->getMessage()),
                    'failure' => true,
                ];
                continue;
            }

            try {
                $this->azureApiClient->addGroupToEnterpriseApp($aadGroup, $googleSuiteProvisioningApplicationId, $googleSuiteProvisioningApplicationRoleId);
            } catch (ClientException $e) {
                $result[$teamName] = [
                    'message' => 'Unable to add the Azure AD group to the Google Suite Provisioning application',
                    'skipped' => false,
                ];
                continue;
            }

            try {
                $githubTeam = $this->githubApiClient->createTeam($teamName, $aadGroup);
            } catch (ClientException $e) {
                $result[$teamName] = [
                    'message' => sprintf('Unable to create GitHub team "%s". Error message: %s', $teamName, $e->getMessage()),
                    'failure' => true,
                ];
                continue;
            }

            try {
                $this->githubApiClient->syncTeamAndGroup($githubTeam, $aadGroup);
            } catch (ClientException $e) {
                $result[$teamName] = [
                    'message' => sprintf('Unable to sync GitHub team and Azure AD group. Error message: %s', $e->getMessage()),
                    'failure' => true,
                ];
                continue;
            }

            try {
                $this->naisDeploymentApiClient->provisionTeamKey($teamName);
            } catch (ClientException $e) {
                $result[$teamName] = [
                    'message' => sprintf('Unable to create Nais deployment key. Error message: %s', $e->getMessage()),
                    'failure' => true,
                ];
                continue;
            }

            $result[$teamName] = [
                'message' => sprintf('Team added: %s', $teamName),
            ];
        }

        return $result;
    }
}