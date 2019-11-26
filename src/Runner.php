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
     * @param AzureApiClient $azureApiClient
     * @param GitHubApiClient $githubApiClient
     * @param NaisDeploymentApiClient $naisDeploymentApiClient
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
            $teamResult = new TeamResult($teamName);

            $aadGroup = $this->azureApiClient->getGroupByName($teamName);

            if (null !== $aadGroup) {
                $result[$teamName] = $teamResult->skip(sprintf(
                    'Group already exists in Azure AD with ID "%s", skipping...',
                    $aadGroup->getId()
                ));
                continue;
            }

            $githubTeam = $this->githubApiClient->getTeam($teamName);

            if (null !== $githubTeam) {
                $result[$teamName] = $teamResult->skip(sprintf(
                    'Team "%s" (ID: %d) already exists on GitHub, skipping...',
                    $teamName,
                    $githubTeam->getId())
                );
                continue;
            }

            try {
                $aadGroup = $this->azureApiClient->createGroup($teamName, [$userObjectId], [$userObjectId]);
            } catch (ClientException $e) {
                $result[$teamName] = $teamResult->fail(sprintf(
                    'Unable to create Azure AD group: "%s". Error message: %s',
                    $teamName,
                    $e->getMessage())
                );
                continue;
            }

            $teamResult->setGroupId($aadGroup->getId());

            try {
                $this->azureApiClient->addGroupToEnterpriseApp($aadGroup, $googleSuiteProvisioningApplicationId, $googleSuiteProvisioningApplicationRoleId);
            } catch (ClientException $e) {
                $result[$teamName] = $teamResult->fail('Unable to add the Azure AD group to the Google Suite Provisioning application');
                continue;
            }

            try {
                $githubTeam = $this->githubApiClient->createTeam($teamName);
            } catch (ClientException $e) {
                $result[$teamName] = $teamResult->fail(sprintf(
                    'Unable to create GitHub team "%s". Error message: %s',
                    $teamName,
                    $e->getMessage())
                );
                continue;
            }

            try {
                $this->githubApiClient->syncTeamAndGroup($githubTeam, $aadGroup);
            } catch (ClientException $e) {
                $result[$teamName] = $teamResult->fail(sprintf(
                    'Unable to sync GitHub team and Azure AD group. Error message: %s',
                    $e->getMessage()
                ));
                continue;
            }

            try {
                $this->naisDeploymentApiClient->provisionTeamKey($teamName);
            } catch (ClientException $e) {
                $result[$teamName] = $teamResult->fail(sprintf(
                    'Unable to create Nais deployment key. Error message: %s',
                    $e->getMessage()
                ));
                continue;
            }

            $result[$teamName] = $teamResult;
        }

        return $result;
    }
}