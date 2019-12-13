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
     * Whether or not the result will fail the run
     *
     * @var bool
     */
    private $fail = false;

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
     * Set failure
     *
     * @return void
     */
    public function fail() : void {
        $this->fail = true;
    }

    /**
     * Check if the result is a failure
     *
     * @return bool
     */
    public function isFailure() : bool {
        return $this->fail;
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