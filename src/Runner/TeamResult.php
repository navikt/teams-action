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

    public function getTeamName() : string {
        return $this->teamName;
    }

    public function getMessage() : string {
        return $this->message;
    }

    public function added() : bool {
        return self::TEAM_ADDED === $this->result;
    }

    public function failure() : bool {
        return self::TEAM_FAILURE === $this->result;
    }

    public function skipped() : bool {
        return self::TEAM_SKIPPED === $this->result;
    }
}