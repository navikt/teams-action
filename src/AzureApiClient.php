<?php declare(strict_types=1);
namespace NAV\Teams;

use NAV\Teams\Models\AzureAdGroup;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;

class AzureApiClient {
    private $httpClient;
    private $baseUri = 'https://graph.microsoft.com/beta/';

    public function __construct(string $id, string $secret, HttpClient $authClient = null, HttpClient $httpClient = null) {
        $response = ($authClient ?: new HttpClient())->post('https://login.microsoftonline.com/nav.no/oauth2/v2.0/token', [
            'form_params' => [
                'client_id'     => $id,
                'client_secret' => $secret,
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

    public function getGroupById(string $id) : ?AzureAdGroup {
        try {
            $response = $this->httpClient->get(sprintf('groups/%s', $id));
        } catch (ClientException $e) {
            return null;
        }

        return AzureAdGroup::fromArray(json_decode($response->getBody()->getContents(), true));
    }

    public function getGroupByName(string $name) : ?AzureAdGroup {
        try {
            $response = $this->httpClient->get('groups', [
                'query' => [
                    '$filter' => sprintf('displayName eq \'%s\'', $name),
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

    public function createGroup(string $name, array $owners = [], array $members = []) : AzureAdGroup {
        $prefixer = function(string $user) : string {
            return sprintf('%s/users/%s', rtrim($this->baseUri, '/'), $user);
        };

        $response = $this->httpClient->post('groups', [
            'json' => array_filter([
                'displayName'        => $name,
                'description'        => 'Team group created by https://github.com/navikt/teams',
                'securityEnabled'    => true,
                'mailEnabled'        => true,
                'mailNickname'       => $name,
                'groupTypes'         => ['unified'],
                'visibility'         => 'Private',
                'owners@odata.bind'  => array_map($prefixer, $owners),
                'members@odata.bind' => array_map($prefixer, $members),
            ]),
        ]);

        return AzureAdGroup::fromArray(json_decode($response->getBody()->getContents(), true));
    }

    public function addGroupToEnterpriseApp(AzureAdGroup $group, string $applicationObjectId, string $appRoleId) : void {
        $this->httpClient->post(sprintf('servicePrincipals/%s/appRoleAssignments', $applicationObjectId), [
            'json' => [
                'principalId' => $group->getId(),
                'appRoleId'   => $appRoleId,
                'resourceId'  => $applicationObjectId,
            ],
        ]);
    }
}