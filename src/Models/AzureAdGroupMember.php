<?php declare(strict_types=1);
namespace NAV\Teams\Models;

use NAV\Teams\Exceptions\InvalidArgumentException;

class AzureAdGroupMember {
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
    private $mail;

    /**
     * Class constructor
     *
     * @param string $id The ID of the group
     * @param string $displayName The display name of the group
     * @param string $mail The mail address for the group
     */
    public function __construct(string $id, string $displayName, string $mail) {
        $this->id          = $id;
        $this->displayName = $displayName;
        $this->mail        = $mail;
    }

    /**
     * Get the ID
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
     * Get the mail
     *
     * @return string
     */
    public function getMail() : string {
        return $this->mail;
    }

    /**
     * Create an instance from an array
     *
     * @param array $data
     * @throws InvalidArgumentException
     * @return static
     */
    public static function fromArray(array $data) : self {
        foreach (['id', 'displayName', 'mail'] as $required) {
            if (empty($data[$required])) {
                throw new InvalidArgumentException(sprintf('Missing data element: %s', $required));
            }
        }

        return new static(
            $data['id'],
            $data['displayName'],
            $data['mail']
        );
    }
}