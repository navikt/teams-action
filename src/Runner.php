<?php declare(strict_types=1);
namespace NAVIT\Teams;

use NAVIT\Teams\Runner\Output;
use NAVIT\Teams\Runner\Result;
use NAVIT\Teams\Runner\ResultEntry;
use NAVIT\AzureAd\ApiClient as AzureAdApiClient;
use NAVIT\GitHub\ApiClient as GitHubApiClient;
use NAVIT\AzureAd\Models\Group as AzureAdGroup;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Yaml\Yaml;
use InvalidArgumentException;
use RuntimeException;

class Runner {
    /**
     * @var AzureAdApiClient
     */
    private $azureAdApiClient;

    /**
     * @var GitHubApiClient
     */
    private $githubApiClient;

    /**
     * @var NaisDeploymentApiClient
     */
    private $naisDeploymentApiClient;

    /**
     * @var Output
     */
    private $output;

    /**
     * Class constructor
     *
     * @param AzureAdApiClient $azureAdApiClient
     * @param GitHubApiClient $githubApiClient
     * @param NaisDeploymentApiClient $naisDeploymentApiClient
     * @param Output $output
     */
    public function __construct(
        AzureAdApiClient $azureAdApiClient,
        GitHubApiClient $githubApiClient,
        NaisDeploymentApiClient $naisDeploymentApiClient,
        Output $output = null
    ) {
        $this->azureAdApiClient        = $azureAdApiClient;
        $this->githubApiClient         = $githubApiClient;
        $this->naisDeploymentApiClient = $naisDeploymentApiClient;
        $this->output                  = $output ?: new Output();
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
            } else if (0 === preg_match('/^[a-z][a-z0-9-]{2,29}(?<!-)$/', $team['name'])) {
                throw new InvalidArgumentException(sprintf('Invalid team name: %s', $team['name']));
            }
        }
    }

    /**
     * Run the action
     *
     * @param array $teams List of teams
     * @param string $userObjectId ID The Azure AD user object ID that initiated the run
     * @param string $containerApplicationId
     * @param string $containerApplicationRoleId
     * @throws InvalidArgumentException Throws an exception if the teams array is invalid
     * @return Result
     */
    public function run(
        array $teams,
        string $userObjectId,
        string $containerApplicationId,
        string $containerApplicationRoleId
    ) : Result {
        $this->validateTeams($teams);

        $managedTeams = $this->azureAdApiClient->getEnterpriseAppGroups($containerApplicationId);

        if (empty($managedTeams)) {
            throw new RuntimeException('Unable to fetch managed teams, aborting...');
        }

        $isManaged = function(AzureAdGroup $group) use ($managedTeams) {
            $mailNickname = strtolower($group->getMailNickname());

            return 0 !== count(array_filter($managedTeams, function(AzureAdGroup $managedTeam) use ($mailNickname) {
                return $mailNickname === strtolower($managedTeam->getMailNickname());
            }));
        };

        $result = new Result();

        foreach ($teams as $team) {
            $teamName        = $team['name'];
            $teamDescription = $team['description'];
            $resultEntry     = new ResultEntry($teamName);

            $aadGroup = $this->azureAdApiClient->getGroupByMailNickname($teamName);

            if (null !== $aadGroup) {
                if (!$isManaged($aadGroup)) {
                    $this->output->debug($teamName, sprintf(
                        'A non-managed group with this name already exists in Azure AD with ID "%s", skipping...',
                        $aadGroup->getId()
                    ));
                    continue;
                }

                $this->output->debug($teamName, sprintf(
                    'Group already exists in Azure AD (ID "%s")',
                    $aadGroup->getId()
                ));

                if ($aadGroup->getDescription() !== $teamDescription) {
                    $this->output->debug($teamName, 'Group description in Azure AD is out of sync, updating...');
                    $this->azureAdApiClient->setGroupDescription($aadGroup->getId(), $teamDescription);
                }
            } else {
                $this->output->debug($teamName, 'Group does not exist in Azure AD, creating...');

                try {
                    $aadGroup = $this->azureAdApiClient->createGroup($teamName, $teamDescription, [$userObjectId], [$userObjectId]);
                } catch (ClientException $e) {
                    $this->output->failure($teamName, sprintf(
                        'Unable to create Azure AD group, error message: %s',
                        $e->getMessage())
                    );
                    continue;
                }

                $this->output->debug($teamName, sprintf(
                    'Group has been created in Azure AD, ID: "%s"',
                    $aadGroup->getId()
                ));

                try {
                    $this->azureAdApiClient->addGroupToEnterpriseApp($aadGroup->getId(), $containerApplicationId, $containerApplicationRoleId);
                } catch (ClientException $e) {
                    $this->output->failure($teamName, 'Unable to mark the Azure AD group as "managed", continuing to the next team...');
                    continue;
                }
            }

            $resultEntry->setGroupId($aadGroup->getId());
            $result->addEntry($resultEntry);

            $githubTeam = $this->githubApiClient->getTeam($teamName);

            if (null !== $githubTeam) {
                $this->output->debug($teamName, sprintf(
                    'Team already exists on GitHub (ID: %d)',
                    $githubTeam->getId())
                );
            } else {
                $this->output->debug($teamName, 'Team does not exist on GitHub, creating...');

                try {
                    $githubTeam = $this->githubApiClient->createTeam($teamName, $teamDescription);

                    $this->output->debug($teamName, sprintf(
                        'Team has been created on GitHub, ID: %d',
                        $githubTeam->getId()
                    ));

                    $this->output->debug($teamName, 'Enable sync between Azure AD group and GitHub team...');

                    try {
                        $this->githubApiClient->syncTeamAndGroup($githubTeam->getSlug(), $aadGroup->getId(), $aadGroup->getDisplayName(), $aadGroup->getDescription());
                    } catch (ClientException $e) {
                        $this->output->failure($teamName, sprintf(
                            'Unable to sync Azure AD group and GitHub team, error message: %s',
                            $e->getMessage()
                        ));
                    }
                } catch (ClientException $e) {
                    $this->output->failure($teamName, sprintf(
                        'Unable to create GitHub team, error message: %s',
                        $e->getMessage())
                    );
                }
            }

            for ($failures = 0; $failures < 5; $failures++) {
                try {
                    $this->naisDeploymentApiClient->provisionTeamKey($teamName);
                    $this->output->debug($teamName, 'NAIS deployment key has been provisioned');
                    break;
                } catch (ClientException $e) {
                    if ($failures < 4) {
                        $wait = pow(2, $failures + 2) - 1;
                        $this->output->debug($teamName, sprintf(
                            'Unable to provision a NAIS deployment key at the moment, waiting %d second(s)',
                            $wait
                        ));
                        sleep((int) $wait);
                    } else {
                        $this->output->failure($teamName, sprintf(
                            'Unable to provision NAIS deployment key, error message: %s',
                            $e->getMessage()
                        ));
                    }
                }
            }
        }

        return $result;
    }
}