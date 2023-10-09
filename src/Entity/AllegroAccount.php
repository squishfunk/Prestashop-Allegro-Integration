<?php
namespace Allegro\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity()
 */
class AllegroAccount
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_allegro_account", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $name;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean", nullable=true, options={"default" : false})
     */
    private $authorized;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return bool
     */
    public function isAuthorized(): bool
    {
        return $this->authorized;
    }

    /**
     * @param bool $authorized
     */
    public function setAuthorized(bool $authorized): void
    {
        $this->authorized = $authorized;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'name' => $this->getName(),
            'authorized' => $this->isAuthorized(),
        ];
    }
}