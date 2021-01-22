<?php declare(strict_types=1);
namespace NAVIT\Teams;

use GuzzleHttp\Exception\ClientException;
use InvalidArgumentException;
use NAVIT\AzureAd\ApiClient as AzureAdApiClient;
use NAVIT\GitHub\ApiClient as GitHubApiClient;
use NAVIT\Teams\Runner\Output;
use NAVIT\Teams\Runner\Result;
use NAVIT\Teams\Runner\ResultEntry;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class Runner
{
    private AzureAdApiClient $azureAdApiClient;
    private GitHubApiClient $githubApiClient;
    private NaisDeploymentApiClient $naisDeploymentApiClient;
    private Output $output;

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
     * @param array<array{name:string,description:string}> $teams List of teams
     * @throws InvalidArgumentException
     * @return void
     */
    private function validateTeams(array $teams): void
    {
        foreach ($teams as $team) {
            if (empty($team['name'])) {
                throw new InvalidArgumentException(sprintf('Missing team name: %s', Yaml::dump($team)));
            } elseif (empty($team['description'])) {
                throw new InvalidArgumentException(sprintf('Missing team description: %s', Yaml::dump($team)));
            } elseif (0 === preg_match('/^[a-z][a-z0-9-]{2,29}(?<!-)$/', $team['name'])) {
                throw new InvalidArgumentException(sprintf('Invalid team name: %s', $team['name']));
            }
        }
    }

    /**
     * Run the action
     *
     * @param array<array{name:string,description:string}> $teams List of teams
     * @param string $userObjectId ID The Azure AD user object ID that initiated the run
     * @param string $containerApplicationId
     * @param string $containerApplicationRoleId
     * @param string[] $aadOwnerGroups Add the owner to these groups in AAD
     * @throws InvalidArgumentException Throws an exception if the teams array is invalid
     * @return Result
     */
    public function run(
        array $teams,
        string $userObjectId,
        string $containerApplicationId,
        string $containerApplicationRoleId,
        array $aadOwnerGroups = []
    ): Result {
        $this->validateTeams($teams);

        $managedTeams = array_filter(
            $this->azureAdApiClient->getEnterpriseAppGroups($containerApplicationId),
            fn (array $group): bool => !empty($group['mailNickname']),
        );

        if (empty($managedTeams)) {
            throw new RuntimeException('Unable to fetch managed teams, aborting...');
        }

        $isManaged = fn (array $group): bool =>
            0 !== count(array_filter(
                $managedTeams,
                fn (array $managedTeam) =>
                    strtolower((string) $group['mailNickname']) === strtolower((string) $managedTeam['mailNickname'])
            ));

        $result = new Result();

        foreach ($teams as $team) {
            $teamName        = $team['name'];
            $teamDescription = $team['description'];
            $resultEntry     = new ResultEntry($teamName);

            /** @var ?array{id:string,displayName:string,mailNickname:string,description:string} */
            $aadGroup = $this->azureAdApiClient->getGroupByMailNickname($teamName);

            if (null !== $aadGroup) {
                if (!$isManaged($aadGroup)) {
                    $this->output->debug($teamName, sprintf(
                        'A non-managed group with this name already exists in Azure AD with ID "%s", skipping...',
                        $aadGroup['id']
                    ));
                    continue;
                }

                $this->output->debug($teamName, sprintf(
                    'Group already exists in Azure AD (ID "%s")',
                    $aadGroup['id']
                ));

                if ($aadGroup['description'] !== $teamDescription) {
                    $this->output->debug($teamName, 'Group description in Azure AD is out of sync, updating...');
                    $this->azureAdApiClient->setGroupDescription($aadGroup['id'], $teamDescription);
                }
            } else {
                $this->output->debug($teamName, 'Group does not exist in Azure AD, creating...');

                try {
                    /** @var array{id:string,displayName:string,mailNickname:string,description:string} */
                    $aadGroup = $this->azureAdApiClient->createGroup($teamName, $teamDescription, [$userObjectId], [$userObjectId]);
                } catch (ClientException $e) {
                    $this->output->failure(
                        $teamName,
                        sprintf(
                            'Unable to create Azure AD group, error message: %s',
                            $e->getMessage()
                        )
                    );
                    continue;
                }

                $this->output->debug($teamName, sprintf(
                    'Group has been created in Azure AD, ID: "%s"',
                    $aadGroup['id']
                ));

                try {
                    $this->azureAdApiClient->addGroupToEnterpriseApp($aadGroup['id'], $containerApplicationId, $containerApplicationRoleId);
                } catch (ClientException $e) {
                    $this->output->failure($teamName, 'Unable to mark the Azure AD group as "managed", continuing to the next team...');
                    continue;
                }
            }

            $resultEntry->setGroupId($aadGroup['id']);
            $result->addEntry($resultEntry);

            /** @var ?array{id:int,name:string,slug:string} */
            $githubTeam = $this->githubApiClient->getTeam($teamName);

            if (null !== $githubTeam) {
                $this->output->debug(
                    $teamName,
                    sprintf(
                        'Team already exists on GitHub (ID: %d)',
                        $githubTeam['id']
                    )
                );
            } else {
                $this->output->debug($teamName, 'Team does not exist on GitHub, creating...');

                try {
                    /** @var array{id:int,name:string,slug:string} */
                    $githubTeam = $this->githubApiClient->createTeam($teamName, $teamDescription);

                    $this->output->debug($teamName, sprintf(
                        'Team has been created on GitHub, ID: %d',
                        $githubTeam['id']
                    ));

                    $this->output->debug($teamName, 'Enable sync between Azure AD group and GitHub team...');

                    try {
                        $this->githubApiClient->syncTeamAndGroup($githubTeam['slug'], $aadGroup['id'], $aadGroup['displayName'], $aadGroup['description']);
                    } catch (ClientException $e) {
                        $this->output->failure($teamName, sprintf(
                            'Unable to sync Azure AD group and GitHub team, error message: %s',
                            $e->getMessage()
                        ));
                    }
                } catch (ClientException $e) {
                    $this->output->failure(
                        $teamName,
                        sprintf(
                            'Unable to create GitHub team, error message: %s',
                            $e->getMessage()
                        )
                    );
                }
            }

            $this->provisionTeamKey($teamName);
        }

        $this->addOwnerToGroups($userObjectId, $aadOwnerGroups);

        return $result;
    }

    /**
     * Provision a team key
     *
     * @param string $teamName
     * @return void
     */
    private function provisionTeamKey(string $teamName): void
    {
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

    /**
     * Add the committer to a list of groups
     *
     * @param string $userId
     * @param string[] $groupIds
     * @return void
     */
    private function addOwnerToGroups(string $userId, array $groupIds): void
    {
        foreach ($groupIds as $groupId) {
            try {
                $this->azureAdApiClient->addUserToGroup($userId, $groupId);
            } catch (RuntimeException $e) {
                // Ignore
            }
        }
    }
}
