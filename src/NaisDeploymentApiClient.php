<?php declare(strict_types=1);
namespace NAVIT\Teams;

use GuzzleHttp\{
    Client as HttpClient,
    HandlerStack,
    Middleware,
    Psr7\Request,
};

class NaisDeploymentApiClient {
    private HttpClient $httpClient;

    /**
     * Class constructor
     *
     * @param string $secret Hex-encoded secret
     * @param HttpClient $httpClient Pre-configured HTTP client instance
     */
    public function __construct(string $secret, HttpClient $httpClient = null) {
        if (null === $httpClient) {
            $handler = HandlerStack::create();
            $handler->unshift(Middleware::mapRequest(fn(Request $request) : Request =>
                $request->withHeader(
                    'x-nais-signature',
                    hash_hmac(
                        'sha256',
                        $request->getBody()->getContents(),
                        (string) hex2bin($secret)
                    )
                )
            ));

            $httpClient = new HttpClient([
                'base_uri' => 'https://deploy.nais.io/api/v1/',
                'handler'  => $handler,
            ]);
        }

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
