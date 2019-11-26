<?php declare(strict_types=1);
namespace NAV\Teams\Models;

use NAV\Teams\Exceptions\InvalidArgumentException;

class AzureAdGroup {
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $displayName;

    /**
     * @var string
     */
    private $description;

    /**
     * Class constructor
     *
     * @param string $id The ID of the group
     * @param string $displayName The display name of the group
     * @param string $description The description of the group
     */
    public function __construct(string $id, string $displayName, string $description) {
        $this->id = $id;
        $this->displayName = $displayName;
        $this->description = $description;
    }

    /**
     * Get the group ID
     *
     * @return string
     */
    public function getId() : string {
        return $this->id;
    }

    /**
     * Get the display name
     *
     * @return string
     */
    public function getDisplayName() : string {
        return $this->displayName;
    }

    /**
     * Get the description
     *
     * @return string
     */
    public function getDescription() : string {
        return $this->description;
    }

    /**
     * Create an instance from an array
     *
     * @param array $data
     * @throws InvalidArgumentException
     * @return self
     */
    public static function fromArray(array $data) : self {
        foreach (['id', 'displayName', 'description'] as $required) {
            if (empty($data[$required])) {
                throw new InvalidArgumentException(sprintf('Missing data element: %s', $required));
            }
        }

        return new self(
            $data['id'],
            $data['displayName'],
            $data['description']
        );
    }
}