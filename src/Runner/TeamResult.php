<?php declare(strict_types=1);
namespace NAV\Teams\Runner;

class TeamResult {
    const TEAM_SKIPPED = 0;
    const TEAM_ADDED   = 1;
    const TEAM_FAILURE = 2;

    /**
     * @var string
     */
    private $teamName;

    /**
     * @var string
     */
    private $message;

    /**
     * @var int
     */
    private $result;

    /**
     * Class constructor
     *
     * @param string $teamName Name of the team
     * @param string $message Status message
     * @param int $result The result of the operation
     */
    public function __construct(string $teamName, string $message, int $result = self::TEAM_SKIPPED) {
        $this->teamName = $teamName;
        $this->message  = $message;
        $this->result   = $result;
    }

    /**
     * Get the team name
     */
    public function getTeamName() : string {
        return $this->teamName;
    }

    /**
     * Get the result message
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
}