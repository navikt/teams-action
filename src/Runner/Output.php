<?php declare(strict_types=1);
namespace NAVIT\Teams\Runner;

class Output {
    /**
     * Message class
     *
     * @var string
     */
    const DEBUG   = 'DEBUG';
    const FAILURE = 'FAILURE';

    /**
     * All messages
     *
     * @var array<int, array{type: string, team: string, message: string}>
     */
    private $messages = [];

    /**
     * Output a debug message
     *
     * @param string $teamName The name of the team
     * @param string $message The message
     * @return void
     */
    public function debug(string $teamName, string $message) : void {
        $message = $this->normalize($message);

        $this->messages[] = [
            'type'    => self::DEBUG,
            'team'    => $teamName,
            'message' => $message,
        ];

        echo sprintf('[%s] %s', $teamName, $message) . PHP_EOL;
    }

    /**
     * Output a failure message
     *
     * @param string $teamName The name of the team
     * @param string $message The message
     * @return void
     */
    public function failure(string $teamName, string $message) : void {
        $message = $this->normalize($message);

        $this->messages[] = [
            'type'    => self::FAILURE,
            'team'    => $teamName,
            'message' => $message,
        ];

        echo sprintf('[%s] %s', $teamName, $message) . PHP_EOL;
    }

    /**
     * Check if there are any failures in the output
     *
     * @return bool
     */
    public function hasFailures() : bool {
        return 0 !== count(array_filter($this->messages, function(array $message) : bool {
            return $message['type'] === self::FAILURE;
        }));
    }

    /**
     * Normalize a message
     *
     * @param string $message
     * @return string
     */
    private function normalize(string $message) : string {
        return trim($message);
    }
}