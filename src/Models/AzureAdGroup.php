<?php declare(strict_types=1);
namespace NAV\Teams\Models;

use NAV\Teams\Exceptions\InvalidArgumentException;

class AzureAdGroup {
    private $id;
    private $displayName;
    private $description;

    public function __construct(string $id, string $displayName, string $description = null) {
        $this->id = $id;
        $this->displayName = $displayName;
        $this->description = $description;
    }

    public function getId() : string {
        return $this->id;
    }

    public function getDisplayName() : string {
        return $this->displayName;
    }

    public function getDescription() : ?string {
        return $this->description;
    }

    public static function fromArray(array $data) : self {
        foreach (['id', 'displayName'] as $required) {
            if (empty($data[$required])) {
                throw new InvalidArgumentException(sprintf('Missing data element: %s', $required));
            }
        }

        return new self(
            $data['id'],
            $data['displayName'],
            $data['description'] ?? null
        );
    }
}