<?php declare(strict_types=1);
namespace NAV\Teams;

use NAV\Teams\Models\AzureAdGroup;
use NAV\Teams\Models\GitHubTeam;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;

class GitHubApiClient {
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * Class constructor
     *
     * @param string $personalAccessToken The personal access token to use
     * @param HttpClient $httpClient Pre-configured HTTP client to use
     */
    public function __construct(string $personalAccessToken, HttpClient $httpClient = null) {
        $this->httpClient = $httpClient ?: new HttpClient([
            'base_uri' => 'https://api.github.com/',
            'auth' => ['x-access-token', $personalAccessToken],
            'headers' => [
                'Accept' => 'application/json'
            ],
        ]);
    }

    /**
     * Get a team by name
     *
     * @param string $name The name of the team
     * @throws ClientException
     * @return GitHubTeam|null
     */
    public function getTeam(string $name) : ?GitHubTeam {
        try {
            $response = $this->httpClient->get(sprintf('orgs/navikt/teams/%s', $name));
        } catch (ClientException $e) {
            if (404 === $e->getCode()) {
                return null;
            }

            throw $e;
        }

        return GitHubTeam::fromArray(json_decode($response->getBody()->getContents(), true));
    }

    /**
     * Create a new team
     *
     * @param string $name The name of the team
     * @param string $description The description of the team
     * @throws ClientException
     * @return GitHubTeam
     */
    public function createTeam(string $name, string $description) : GitHubTeam {
        $response = $this->httpClient->post('orgs/navikt/teams', [
            'json' => [
                'name'        => $name,
                'description' => $description,
                'privacy'     => 'closed'
            ],
        ]);

        return GitHubTeam::fromArray(json_decode($response->getBody()->getContents(), true));
    }

    /**
     * Get the SAML ID (Azure AD email address) from a GitHub login
     *
     * This process is slow because of the way the API is designed. There is no way to filter on
     * a specific user, so we need to loop over all entities governed by SCIM until we find the
     * matching GitHub login. Until GitHub fixes their API this is the only solution.
     *
     * @param string $username The GitHub login
     * @throws ClientException
     * @return string|null
     */
    public function getSamlId(string $username) : ?string {
        $offset = null;
        $query = <<<GQL
        query {
            organization(login: "navikt") {
                samlIdentityProvider {
                    externalIdentities(first: 100 %s) {
                        pageInfo {
                            endCursor
                            startCursor
                            hasNextPage
                        }
                        nodes {
                            samlIdentity {
                                nameId
                            }
                            user {
                                login
                            }
                        }
                    }
                }
            }
        }
GQL;

        do {
            $response = $this->httpClient->post('graphql', [
                'json' => ['query' => sprintf($query, $offset ? sprintf('after: "%s"', $offset) : '')],
            ]);

            $data     = json_decode($response->getBody()->getContents(), true);
            $pageInfo = $data['data']['organization']['samlIdentityProvider']['externalIdentities']['pageInfo'];
            $nodes    = $data['data']['organization']['samlIdentityProvider']['externalIdentities']['nodes'];
            $offset   = $pageInfo['endCursor'];

            foreach ($nodes as $entity) {
                if ($entity['user']['login'] === $username) {
                    return $entity['samlIdentity']['nameId'];
                }
            }
        } while ($pageInfo['hasNextPage']);

        return null;
    }

    /**
     * Connect a GitHub team with an Azure AD group
     *
     * @param GitHubTeam $team The GitHub team
     * @param AzureAdGroup $aadGroup The Azure AD group
     * @throws ClientException
     * @return bool
     */
    public function syncTeamAndGroup(GitHubTeam $team, AzureAdGroup $aadGroup) : bool {
        $this->httpClient->patch(sprintf('teams/%d/team-sync/group-mappings', $team->getId()), [
            'json' => [
                'groups' => [[
                    'group_id'          => $aadGroup->getId(),
                    'group_name'        => $aadGroup->getDisplayName(),
                    'group_description' => $aadGroup->getDescription(),
                ]]
            ]
        ]);

        return true;
    }
}