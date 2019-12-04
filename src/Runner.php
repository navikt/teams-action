<?php declare(strict_types=1);
namespace NAV\Teams;

use NAV\Teams\Runner\TeamResult;
use NAV\Teams\Exceptions\InvalidArgumentException;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Yaml\Yaml;

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
     * Validate the teams list
     *
     * @param array $teams List of teams
     * @throws InvalidArgumentException
     * @return void
     */
    private function validateTeams(array $teams) : void {
        foreach ($teams as $team) {
            if (empty($team['name'])) {
                throw new InvalidArgumentException(sprintf('Missing team name: %s', Yaml::dump($team)));
            } else if (empty($team['description'])) {
                throw new InvalidArgumentException(sprintf('Missing team description: %s', Yaml::dump($team)));
            } else if (0 === preg_match('/^[a-z][a-z0-9-]{3,29}(?<!-)$/', $team['name'])) {
                throw new InvalidArgumentException(sprintf('Invalid team name: %s', $team['name']));
            }
        }
    }

    /**
     * Run the action
     *
     * @param array $teams List of teams
     * @param string $userObjectId ID The Azure AD user object ID that initiated the run
     * @param string $googleSuiteProvisioningApplicationId
     * @param string $googleSuiteProvisioningApplicationRoleId
     * @param string $containerApplicationId
     * @param string $containerApplicationRoleId
     * @throws InvalidArgumentException Throws an exception if the teams array is invalid
     * @return TeamResult[]
     */
    public function run(
        array $teams,
        string $userObjectId,
        string $googleSuiteProvisioningApplicationId,
        string $googleSuiteProvisioningApplicationRoleId,
        string $containerApplicationId,
        string $containerApplicationRoleId
    ) : array {
        $this->validateTeams($teams);

        $result = [];

        foreach ($teams as $team) {
            $teamName        = $team['name'];
            $teamDescription = $team['description'];
            $teamResult      = new TeamResult($teamName);

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
                $aadGroup = $this->azureApiClient->createGroup($teamName, $teamDescription, [$userObjectId], [$userObjectId]);
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
                $this->azureApiClient->addGroupToEnterpriseApp($aadGroup, $containerApplicationId, $containerApplicationRoleId);
            } catch (ClientException $e) {
                $result[$teamName] = $teamResult->fail('Unable to add the Azure AD group to the teams management application');
                continue;
            }

            try {
                $this->azureApiClient->addGroupToEnterpriseApp($aadGroup, $googleSuiteProvisioningApplicationId, $googleSuiteProvisioningApplicationRoleId);
            } catch (ClientException $e) {
                $result[$teamName] = $teamResult->fail('Unable to add the Azure AD group to the Google Suite Provisioning application');
                continue;
            }

            try {
                $githubTeam = $this->githubApiClient->createTeam($teamName, $teamDescription);
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