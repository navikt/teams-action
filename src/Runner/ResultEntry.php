<?php declare(strict_types=1);
namespace NAVIT\Teams\Runner;

use JsonSerializable;

class ResultEntry implements JsonSerializable {
    private string $teamName;
    private ?string $groupId = null;

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
     * @return array{teamName:string,groupId:?string}
     */
    public function jsonSerialize() : array {
        return [
            'teamName' => $this->teamName,
            'groupId'  => $this->groupId,
        ];
    }
}