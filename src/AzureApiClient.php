<?php declare(strict_types=1);
namespace NAV\Teams;

use NAV\Teams\Models\AzureAdGroup;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use NAV\Teams\Exceptions\InvalidArgumentException;
use NAV\Teams\Models\AzureAdGroupMember;
use NAV\Teams\Models\AzureAdGroupOwner;

class AzureApiClient {
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var string
     */
    private $baseUri = 'https://graph.microsoft.com/beta/';

    /**
     * Class constructor
     *
     * @param string $clientId Client ID
     * @param string $clientSecret Client secret
     * @param HttpClient $authClient Pre-configured HTTP client for auth
     * @param HttpClient $httpClient Pre-configured HTTP client for the API calls
     */
    public function __construct(string $clientId, string $clientSecret, HttpClient $authClient = null, HttpClient $httpClient = null) {
        $response = ($authClient ?: new HttpClient())->post('https://login.microsoftonline.com/nav.no/oauth2/v2.0/token', [
            'form_params' => [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ],
        ]);

        $response = json_decode((string) $response->getBody(), true);

        $this->httpClient = $httpClient ?: new HttpClient([
            'base_uri' => $this->baseUri,
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => sprintf('Bearer %s', $response['access_token']),
            ],
        ]);
    }

    /**
     * Get an Azure AD group by ID
     *
     * @param string $groupId The ID of the group
     * @return AzureAdGroup|null
     */
    public function getGroupById(string $groupId) : ?AzureAdGroup {
        try {
            $response = $this->httpClient->get(sprintf('groups/%s', $groupId));
        } catch (ClientException $e) {
            return null;
        }

        return AzureAdGroup::fromArray(json_decode($response->getBody()->getContents(), true));
    }

    /**
     * Get an Azure AD group by name
     *
     * @param string $groupName The display name of the group
     * @return AzureAdGroup|null
     */
    public function getGroupByName(string $groupName) : ?AzureAdGroup {
        try {
            $response = $this->httpClient->get('groups', [
                'query' => [
                    '$filter' => sprintf('displayName eq \'%s\'', $groupName),
                ],
            ]);
        } catch (ClientException $e) {
            return null;
        }

        $groups = json_decode($response->getBody()->getContents(), true);

        return !empty($groups['value'])
            ? AzureAdGroup::fromArray($groups['value'][0])
            : null;
    }

    /**
     * Create a group
     *
     * @param string $groupName The name of the group
     * @param string $description The description of the group
     * @param string[] $owners List of users to be added as owners
     * @param string[] $members List of users to be added as members
     * @return AzureAdGroup
     */
    public function createGroup(string $groupName, string $description, array $owners = [], array $members = []) : AzureAdGroup {
        $prefixer = function(string $user) : string {
            return sprintf('%s/users/%s', rtrim($this->baseUri, '/'), $user);
        };

        $response = $this->httpClient->post('groups', [
            'json' => array_filter([
                'displayName'        => $groupName,
                'description'        => $description,
                'securityEnabled'    => true,
                'mailEnabled'        => true,
                'mailNickname'       => $groupName,
                'groupTypes'         => ['unified'],
                'visibility'         => 'Private',
                'owners@odata.bind'  => array_map($prefixer, $owners),
                'members@odata.bind' => array_map($prefixer, $members),
            ]),
        ]);

        return AzureAdGroup::fromArray(json_decode($response->getBody()->getContents(), true));
    }

    /**
     * Set group description
     *
     * @param string $groupId The ID of the group
     * @param string $description The new description
     * @return bool Returns true on success or false otherwise
     */
    public function setGroupDescription(string $groupId, string $description) : bool {
        try {
            $this->httpClient->patch(sprintf('groups/%s', $groupId), [
                'json' => [
                    'description' => $description,
                ],
            ]);
        } catch (ClientException $e) {
            return false;
        }

        return true;
    }

    /**
     * Add Azure AD group to an enterprise application
     *
     * @param AzureAdGroup $group The group to add
     * @param string $applicationObjectId The object ID of the application to add the group to
     * @param string $applicationRoleId The role ID the group will receive
     * @return void
     */
    public function addGroupToEnterpriseApp(AzureAdGroup $group, string $applicationObjectId, string $applicationRoleId) : void {
        $this->httpClient->post(sprintf('servicePrincipals/%s/appRoleAssignments', $applicationObjectId), [
            'json' => [
                'principalId' => $group->getId(),
                'appRoleId'   => $applicationRoleId,
                'resourceId'  => $applicationObjectId,
            ],
        ]);
    }

    /**
     * Get all groups connected to a specific enterprise application in Azure AD
     *
     * @param string $applicationObjectId The object ID of the application
     * @return AzureAdGroup[]
     */
    public function getEnterpriseAppGroups(string $applicationObjectId) : array {
        $url = sprintf('servicePrincipals/%s/appRoleAssignedTo', $applicationObjectId);
        $query = [
            '$select' => join(',', ['principalId', 'principalType']),
            '$top'    => 100
        ];

        return array_map(function(array $group) : AzureAdGroup {
            return $this->getGroupById($group['principalId']);
        }, array_filter($this->getPaginatedData($url, $query), function(array $group) : bool {
            return 'group' === strtolower($group['principalType']);
        }));
    }

    /**
     * Get all members in a group
     *
     * @param AzureAdGroup $group The group
     * @return AzureAdGroupMember[] Returns an array of users
     */
    public function getGroupMembers(AzureAdGroup $group) : array {
        return array_filter(array_map(function(array $member) : ?AzureAdGroupMember {
            try {
                return AzureAdGroupMember::fromArray($member);
            } catch (InvalidArgumentException $e) {
                return null;
            }
        }, $this->getPaginatedData(sprintf('groups/%s/members', $group->getId()), [
            '$select' => join(',', ['id', 'displayName', 'mail']),
            '$top' => 100
        ])));
    }

    /**
     * Get all owners of a group
     *
     * @param AzureAdGroup $group The group
     * @return AzureAdGroupOwner[] Returns an array of users
     */
    public function getGroupOwners(AzureAdGroup $group) : array {
        return array_filter(array_map(function(array $member) : ?AzureAdGroupOwner {
            try {
                return AzureAdGroupOwner::fromArray($member);
            } catch (InvalidArgumentException $e) {
                return null;
            }
        }, $this->getPaginatedData(sprintf('groups/%s/owners', $group->getId()), [
            '$select' => join(',', ['id', 'displayName', 'mail']),
            '$top' => 100
        ])));
    }

    /**
     * Get paginated data from the API
     *
     * @param string $url The URL to fetch
     * @param array $query Query parameters
     * @return array
     */
    private function getPaginatedData(string $url, array $query = []) : array {
        $entries = [];

        while ($url) {
            $response = $this->httpClient->get($url, ['query' => $query]);
            $body = json_decode($response->getBody()->getContents(), true);
            $entries = array_merge($entries, $body['value']);
            $url = $body['@odata.nextLink'] ?? null;
            $query = []; // Only need this for the first request
        }

        return $entries;
    }
}