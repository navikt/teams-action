<?php declare(strict_types=1);
namespace NAV\Teams\Runner;

use JsonSerializable;

class ResultEntry implements JsonSerializable {
    /**
     * @var string
     */
    private $teamName;

    /**
     * Azure AD group ID
     *
     * @var string
     */
    private $groupId;

    /**
     * Class constructor
     *
     * @param string $teamName The name of the team
     */
    public function __construct(string $teamName) {
        $this->teamName = $teamName;
    }

    /**
     * Set the group ID
     *
     * @param string $groupId
     * @return void
     */
    public function setGroupId(string $groupId) : void {
        $this->groupId = $groupId;
    }

    /**
     * Serialize as JSON
     *
     * @return array
     */
    public function jsonSerialize() : array {
        return [
            'teamName' => $this->teamName,
            'groupId'  => $this->groupId,
        ];
    }
}