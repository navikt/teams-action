<?php declare(strict_types=1);
namespace NAV\Teams\Runner;

use JsonSerializable;

class TeamResult implements JsonSerializable {
    const TEAM_SKIPPED = 'skipped';
    const TEAM_ADDED   = 'added';
    const TEAM_FAILURE = 'failure';

    /**
     * @var string
     */
    private $teamName;

    /**
     * @var string
     */
    private $message;

    /**
     * @var string
     */
    private $result;

    /**
     * Class constructor
     *
     * @param string $teamName Name of the team
     * @param string $message Status message
     * @param string $result The result of the operation
     */
    public function __construct(string $teamName, string $message, string $result = self::TEAM_SKIPPED) {
        $this->teamName = $teamName;
        $this->message  = $message;
        $this->result   = $result;
    }

    /**
     * Get the team name
     *
     * @return string
     */
    public function getTeamName() : string {
        return $this->teamName;
    }

    /**
     * Get the result message
     *
     * @return string
     */
    public function getMessage() : string {
        return $this->message;
    }

    /**
     * Check if the team was added
     *
     * @return bool
     */
    public function added() : bool {
        return self::TEAM_ADDED === $this->result;
    }

    /**
     * Check if the operation was a failure
     *
     * @return bool
     */
    public function failure() : bool {
        return self::TEAM_FAILURE === $this->result;
    }

    /**
     * Check if the team was skipped
     *
     * @return bool
     */
    public function skipped() : bool {
        return self::TEAM_SKIPPED === $this->result;
    }

    /**
     * Serialze as JSON
     *
     * @return array
     */
    public function jsonSerialize() : array {
        return [
            'team'    => $this->getTeamName(),
            'message' => $this->getMessage(),
            'result'  => $this->result,
        ];
    }
}