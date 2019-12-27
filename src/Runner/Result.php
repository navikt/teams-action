<?php declare(strict_types=1);
namespace NAV\Teams\Runner;

use JsonSerializable;

class Result implements JsonSerializable {
    /**
     * Result entries
     *
     * @var array
     */
    private $entries = [];

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
     * @return array
     */
    public function jsonSerialize() : array {
        return $this->entries;
    }
}