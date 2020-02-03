<?php declare(strict_types=1);
namespace NAVIT\Teams;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;

class NaisDeploymentApiClient {
    /**
     * Name of the middleware for the signature generation
     *
     * @var string
     */
    const ADD_SIGNATURE_MIDDLEWARE = 'add-signature';

    /**
     * HTTP Client instance
     *
     * @var HttpClient
     */
    private $httpClient;

    /**
     * Class constructor
     *
     * The constructor will add a handler for the HTTP client that handles signature generation
     *
     * @param string $secret Hex-encoded secret
     * @param HttpClient $httpClient Pre-configured HTTP client instance
     */
    public function __construct(string $secret, HttpClient $httpClient = null) {
        if (null === $httpClient) {
            $httpClient = new HttpClient([
                'base_uri' => 'https://deployment.prod-sbs.nais.io/api/v1/',
            ]);
        }

        $httpClient
            ->getConfig('handler')
            ->unshift(Middleware::mapRequest(function(Request $request) use ($secret) : Request {
                return $request->withHeader(
                    'x-nais-signature',
                    hash_hmac(
                        'sha256',
                        $request->getBody()->getContents(),
                        (string) hex2bin($secret)
                    )
                );
            }), self::ADD_SIGNATURE_MIDDLEWARE);

        $this->httpClient = $httpClient;
    }

    /**
     * Provision a team key
     *
     * @param string $team The name of the team
     * @return void
     */
    public function provisionTeamKey(string $team) : void {
        $this->httpClient->post('provision', [
            'json' => [
                'team'      => $team,
                'rotate'    => false,
                'timestamp' => time(),
            ]]
        );
    }
}