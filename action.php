<?php declare(strict_types=1);
namespace NAVIT\Teams;

use GuzzleHttp\Exception\ClientException;
use InvalidArgumentException;
use NAVIT\{
    AzureAd\ApiClient as AzureApiClient,
    GitHub\ApiClient as GitHubApiClient,
};
use RuntimeException;
use Symfony\Component\Yaml\{
    Exception\ParseException,
    Yaml,
};

require __DIR__ . '/vendor/autoload.php';

/**
 * Get an environment variable as a string
 *
 * @param string $name
 * @return string
 */
function env(string $name) : string {
    return trim((string) getenv($name));
}

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
    'NAIS_DEPLOYMENT_API_SECRET',
];

foreach ($requiredEnvVars as $requiredEnvVar) {
    if ('' === env($requiredEnvVar)) {
        output(sprintf('Required ENV var is either missing or empty: "%s"', $requiredEnvVar));
        exit(1);
    }
}

try {
    /** @var array{teams:array<array{name:string,description:string}>} */
    $teamsFile = Yaml::parseFile(env('TEAMS_YAML_PATH'));
} catch (ParseException $e) {
    output(sprintf('Invalid YAML in teams.yml: %s', $e->getMessage()));
    exit(1);
}

$teams = $teamsFile['teams'] ?? [];

if (empty($teams)) {
    output('Team list is empty, exiting...');
    exit;
}

usort($teams, fn(array $a, array $b) : int => strcmp($a['name'], $b['name']));

try {
    $azureApiClient = new AzureApiClient(
        env('AZURE_AD_APP_ID'),
        env('AZURE_AD_APP_SECRET'),
        'nav.no',
    );
} catch (ClientException $e) {
    output(sprintf('Unable to create Azure API client: %s', $e->getMessage()));
    exit(1);
}

$githubApiClient = new GitHubApiClient(
    'navikt',
    env('GITHUB_PAT'),
);

$naisDeploymentApiClient = new NaisDeploymentApiClient(
    env('NAIS_DEPLOYMENT_API_SECRET'),
);

$committer = env('COMMITTER');

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
        env('AZURE_AD_CONTAINER_APP_ID'),
        env('AZURE_AD_CONTAINER_APP_ROLE_ID'),
        array_unique(array_filter(explode(',', str_replace(' ', '', env('AAD_OWNER_GROUPS'))))),
    );
} catch (InvalidArgumentException | RuntimeException $e) {
    output($e->getMessage());
    exit(1);
}

echo PHP_EOL . sprintf('::set-output name=results::%s', json_encode($result));
