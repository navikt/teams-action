<?php declare(strict_types=1);
namespace NAV\Teams;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use GuzzleHttp\Exception\ClientException;

require __DIR__ . '/vendor/autoload.php';

function fail(string $message, int $code = 1) : void {
    echo trim($message) . PHP_EOL;
    exit($code);
}

function debug(string $message) : void {
    echo trim($message) . PHP_EOL;
}

$requiredEnvVars = [
    'AZURE_AD_APP_ID',
    'AZURE_AD_APP_SECRET',
    'AZURE_AD_GOOGLE_PROVISIONING_APP_ID',
    'AZURE_AD_GOOGLE_PROVISIONING_ROLE_ID',
    'GITHUB_ACTOR',
    'GITHUB_PAT',
    'TEAMS_YAML_PATH',
];

foreach ($requiredEnvVars as $requiredEnvVar) {
    if (false === getenv($requiredEnvVar)) {
        fail(sprintf('Missing required ENV var: "%s"', $requiredEnvVar));
    }
}

try {
    $teams = array_map(function(array $team) : string {
        return $team['name'];
    }, Yaml::parseFile(getenv('TEAMS_YAML_PATH'))['teams']);
} catch (ParseException $e) {
    fail(sprintf('Invalid YAML in teams.yml: $s', $e->getMessage()));
}

if (empty($teams)) {
    debug('Team list is empty, exiting...');
    exit;
}

foreach ($teams as $team) {
    if (0 === preg_match('/^[a-z][a-z0-9-]{3,29}(?<!-)$/', $team)) {
        fail(sprintf('Invalid team name: %s', $team));
    }
}

try {
    $azureApiClient = new AzureApiClient(
        getenv('AZURE_AD_APP_ID'),
        getenv('AZURE_AD_APP_SECRET')
    );
} catch (ClientException $e) {
    fail(sprintf('Could not create Azure API client: %s', $e->getMessage()));
}

$githubApiClient = new GitHubApiClient(
    getenv('GITHUB_PAT')
);

$actor = getenv('GITHUB_ACTOR');

try {
    $committerSamlId = $githubApiClient->getSamlId($actor);
} catch (ClientException $e) {
    fail(sprintf('Unable to get SAML ID for actor: %s', $e->getMessage()));
}

if (null === $committerSamlId) {
    fail(sprintf('Unable to find SAML ID for actor: %s', $actor));
}

$googleSuiteProvisioningApplicationId = getenv('AZURE_AD_GOOGLE_PROVISIONING_APP_ID');
$appRoleId = getenv('AZURE_AD_GOOGLE_PROVISIONING_ROLE_ID');

foreach ($teams as $teamName) {
    $aadGroup = $azureApiClient->getGroupByName($teamName);

    if (null !== $aadGroup) {
        echo sprintf(
            'Group "%s" (ID: %s) already exists in Azure AD, skipping...',
            $teamName,
            $aadGroup->getId()
        ) . PHP_EOL;
        continue;
    }

    $githubTeam = $githubApiClient->getTeam($teamName);

    if (null !== $githubTeam) {
        echo sprintf(
            'Team "%s" (ID: %d) already exists on GitHub, skipping...',
            $teamName,
            $githubTeam->getId()
        ) . PHP_EOL;
        continue;
    }

    try {
        $aadGroup = $azureApiClient->createGroup($teamName, [$committerSamlId], [$committerSamlId]);
    } catch (ClientException $e) {
        fail(sprintf('Unable to create Azure AD group: "%s". Error message: %s', $teamName, $e->getMessage()));
    }

    debug(sprintf('Created Azure AD group: %s', $aadGroup->getDisplayName()));

    try {
        $azureApiClient->addGroupToEnterpriseApp($aadGroup, $googleSuiteProvisioningApplicationId, $appRoleId);
    } catch (ClientException $e) {
        fail('Unable to add the Azure AD group to the Google Suite Provisioning application');
    }

    debug('Added Azure AD group to the Google Suite Provisioning Enterprise Application');

    try {
        $githubTeam = $githubApiClient->createTeam($teamName, $aadGroup);
    } catch (ClientException $e) {
        fail(sprintf('Unable to create GitHub team "%s". Error message: %s', $teamName, $e->getMessage()));
    }

    debug(sprintf('Created GitHub team: %s', $githubTeam->getName()));
}