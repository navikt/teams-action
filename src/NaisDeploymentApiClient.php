<?php declare(strict_types=1);
namespace NAVIT\Teams;

use GuzzleHttp\{
    Client as HttpClient,
    Middleware,
    Psr7\Request,
    HandlerStack,
};

class NaisDeploymentApiClient {
    /**
     * Name of the middleware for the signature generation
     *
     * @var string
     */
    const ADD_SIGNATURE_MIDDLEWARE = 'add-signature';

    private HttpClient $httpClient;

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
                'base_uri' => 'https://deploy.nais.io/api/v1/',
            ]);
        }

        /** @var HandlerStack */
        $handler = $httpClient->getConfig('handler');
        $handler->unshift(Middleware::mapRequest(function(Request $request) use ($secret) : Request {
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
