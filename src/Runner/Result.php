<?php declare(strict_types=1);
namespace NAVIT\Teams\Runner;

use JsonSerializable;

class Result implements JsonSerializable {
    /**
     * Result entries
     *
     * @var ResultEntry[]
     */
    private array $entries = [];

    /**
     * Add an entry
     *
     * @param ResultEntry $entry
     * @return void
     */
    public function addEntry(ResultEntry $entry) : void {
        $this->entries[] = $entry;
    }

    /**
     * Serialize as JSON
     *
     * @return ResultEntry[]
     */
    public function jsonSerialize() : array {
        return $this->entries;
    }
}