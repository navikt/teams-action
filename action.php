<?php declare(strict_types=1);
namespace NAVIT\Teams;

use NAVIT\{
    AzureAd\ApiClient as AzureApiClient,
    GitHub\ApiClient as GitHubApiClient,
};
use Symfony\Component\Yaml\{
    Exception\ParseException,
    Yaml,
};
use GuzzleHttp\Exception\ClientException;
use InvalidArgumentException;
use RuntimeException;

require __DIR__ . '/vendor/autoload.php';

/**
 * Output a log message
 *
 * @param string $message The message to output
 * @return void
 */
function output(string $message) : void {
    echo trim($message) . PHP_EOL;
}

/**
 * A list of required environment variables
 *
 * @var string[]
 */
$requiredEnvVars = [
    'AZURE_AD_APP_ID',
    'AZURE_AD_APP_SECRET',
    'AZURE_AD_CONTAINER_APP_ID',
    'AZURE_AD_CONTAINER_APP_ROLE_ID',
    'COMMITTER',
    'GITHUB_PAT',
    'TEAMS_YAML_PATH',
    'NAIS_DEPLOYMENT_API_SECRET'
];

foreach ($requiredEnvVars as $requiredEnvVar) {
    if (false === getenv($requiredEnvVar)) {
        output(sprintf('Missing required ENV var: "%s"', $requiredEnvVar));
        exit(1);
    }
}

try {
    /** @var array{teams:array<array{name:string,description:string}>} */
    $teamsFile = Yaml::parseFile((string) getenv('TEAMS_YAML_PATH'));
} catch (ParseException $e) {
    output(sprintf('Invalid YAML in teams.yml: %s', $e->getMessage()));
    exit(1);
}

$teams = $teamsFile['teams'] ?? [];

if (empty($teams)) {
    output('Team list is empty, exiting...');
    exit;
}

try {
    $azureApiClient = new AzureApiClient(
        (string) getenv('AZURE_AD_APP_ID'),
        (string) getenv('AZURE_AD_APP_SECRET'),
        'nav.no',
    );
} catch (ClientException $e) {
    output(sprintf('Unable to create Azure API client: %s', $e->getMessage()));
    exit(1);
}

$githubApiClient = new GitHubApiClient(
    'navikt',
    (string) getenv('GITHUB_PAT')
);

$naisDeploymentApiClient = new NaisDeploymentApiClient(
    (string) getenv('NAIS_DEPLOYMENT_API_SECRET')
);

$committer = (string) getenv('COMMITTER');

try {
    $committerSamlId = $githubApiClient->getSamlId($committer);
} catch (ClientException $e) {
    output(sprintf('Unable to get SAML ID for committer with username "%s": %s', $committer, $e->getMessage()));
    exit(1);
}

if (null === $committerSamlId) {
    output(sprintf('Unable to find SAML ID for committer "%s"', $committer));
    exit(1);
}

$runner = new Runner($azureApiClient, $githubApiClient, $naisDeploymentApiClient);

try {
    $result = $runner->run(
        $teams,
        (string) $committerSamlId,
        (string) getenv('AZURE_AD_CONTAINER_APP_ID'),
        (string) getenv('AZURE_AD_CONTAINER_APP_ROLE_ID'),
        array_unique(array_filter(explode(',', str_replace(' ', '', (string) getenv('AAD_OWNER_GROUPS'))))),
    );
} catch (InvalidArgumentException | RuntimeException $e) {
    output($e->getMessage());
    exit(1);
}

echo PHP_EOL . sprintf('::set-output name=results::%s', json_encode($result));