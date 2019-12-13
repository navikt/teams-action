<?php declare(strict_types=1);
namespace NAV\Teams;

use NAV\Teams\Exceptions\InvalidArgumentException;
use NAV\Teams\Exceptions\RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use GuzzleHttp\Exception\ClientException;

require __DIR__ . '/vendor/autoload.php';

/**
 * Exit the script with a non-zero code and an error message
 *
 * @param string $message The message to output
 * @param int $code The exit code
 * @return void
 */
function fail(string $message, int $code = 1) : void {
    echo trim($message) . PHP_EOL;
    exit($code);
}

/**
 * Output a debug message
 *
 * @param string $message The message to output
 * @return void
 */
function debug(string $message) : void {
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
    'AZURE_AD_GOOGLE_PROVISIONING_APP_ID',
    'AZURE_AD_GOOGLE_PROVISIONING_APP_ROLE_ID',
    'AZURE_AD_CONTAINER_APP_ID',
    'AZURE_AD_CONTAINER_APP_ROLE_ID',
    'GITHUB_ACTOR',
    'GITHUB_PAT',
    'TEAMS_YAML_PATH',
    'NAIS_DEPLOYMENT_API_SECRET'
];

foreach ($requiredEnvVars as $requiredEnvVar) {
    if (false === getenv($requiredEnvVar)) {
        fail(sprintf('Missing required ENV var: "%s"', $requiredEnvVar));
    }
}

try {
    $teams = Yaml::parseFile(getenv('TEAMS_YAML_PATH'))['teams'];
} catch (ParseException $e) {
    fail(sprintf('Invalid YAML in teams.yml: %s', $e->getMessage()));
}

if (empty($teams)) {
    debug('Team list is empty, exiting...');
    exit;
}

try {
    $azureApiClient = new AzureApiClient(
        getenv('AZURE_AD_APP_ID'),
        getenv('AZURE_AD_APP_SECRET')
    );
} catch (ClientException $e) {
    fail(sprintf('Unable to create Azure API client: %s', $e->getMessage()));
}

$githubApiClient = new GitHubApiClient(
    getenv('GITHUB_PAT')
);

$naisDeploymentApiClient = new NaisDeploymentApiClient(
    getenv('NAIS_DEPLOYMENT_API_SECRET')
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

$runner = new Runner($azureApiClient, $githubApiClient, $naisDeploymentApiClient);

try {
    $result = $runner->run(
        $teams,
        $committerSamlId,
        getenv('AZURE_AD_GOOGLE_PROVISIONING_APP_ID'),
        getenv('AZURE_AD_GOOGLE_PROVISIONING_APP_ROLE_ID'),
        getenv('AZURE_AD_CONTAINER_APP_ID'),
        getenv('AZURE_AD_CONTAINER_APP_ROLE_ID')
    );
} catch (InvalidArgumentException | RuntimeException $e) {
    fail($e->getMessage());
}

echo PHP_EOL . sprintf('::set-output name=results::%s', json_encode($result)) . PHP_EOL;

if ($result->isFailure()) {
    exit(1);
}