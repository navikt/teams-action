<?php declare(strict_types=1);
namespace NAV\Teams\Models;

use NAV\Teams\Exceptions\InvalidArgumentException;

class GitHubTeam {
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * Class constructor
     *
     * @param int $id The ID of the team
     * @param string $name The name of the team
     */
    public function __construct(int $id, string $name) {
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * Get the team ID
     *
     * @return int
     */
    public function getId() : int {
        return $this->id;
    }

    /**
     * Get the team name
     *
     * @return string
     */
    public function getName() : string {
        return $this->name;
    }

    /**
     * Create a team instance from an array
     *
     * @param array $data
     * @throws InvalidArgumentException
     * @return self
     */
    public static function fromArray(array $data) : self {
        foreach (['id', 'name'] as $required) {
            if (empty($data[$required])) {
                throw new InvalidArgumentException(sprintf('Missing data element: %s', $required));
            }
        }

        return new self(
            $data['id'],
            $data['name']
        );
    }
}