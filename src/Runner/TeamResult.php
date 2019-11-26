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
     * @var bool
     */
    private $skipped = false;

    /**
     * @var bool
     */
    private $failed = false;

    /**
     * @var ?string
     */
    private $message;

    /**
     * @var ?string
     */
    private $groupId;

    /**
     * Class constructor
     *
     * @param string $teamName Name of the team
     */
    public function __construct(string $teamName) {
        $this->teamName = $teamName;
    }

    /**
     * Mark the team as skipped and set a status message
     *
     * @param string $message
     * @return self
     */
    public function skip(string $message) : self {
        $this->skipped = true;
        $this->message = $message;

        return $this;
    }

    /**
     * Check if the team was skipped
     *
     * @return bool
     */
    public function skipped() : bool {
        return $this->skipped;
    }

    /**
     * Mark the team as failed and set a status message
     *
     * @param string $message
     * @return self
     */
    public function fail(string $message) : self {
        $this->failed = true;
        $this->message = $message;

        return $this;
    }

    /**
     * Check if result is a failure
     *
     * @return bool
     */
    public function failed() : bool {
        return $this->failed;
    }

    /**
     * Set the group ID
     *
     * @param string $id The Azure AD group ID
     * @return self
     */
    public function setGroupId(string $id) : self {
        $this->groupId = $id;

        return $this;
    }

    /**
     * Get the group ID
     *
     * @return ?string
     */
    public function getGroupId() : ?string {
        return $this->groupId;
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
     * Get the message
     *
     * @return ?string
     */
    public function getMessage() : ?string {
        return $this->message;
    }

    /**
     * Check if the result is successfil
     *
     * @return bool
     */
    public function added() : bool {
        return !$this->skipped() && !$this->failed();
    }

    /**
     * Serialze as JSON
     *
     * @return array
     */
    public function jsonSerialize() : array {
        return [
            'team'    => $this->getTeamName(),
            'added'   => $this->added(),
            'skipped' => $this->skipped(),
            'failed'  => $this->failed(),
            'groupId' => $this->getGroupId(),
            'message' => $this->getMessage(),
        ];
    }
}