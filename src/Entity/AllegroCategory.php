<?php
namespace Allegro\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity()
 */
class AllegroCategory
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_allegro_category", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     */
    private $allegroId;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $name;

    /**
     * @var int|null
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $parentId;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean")
     */
    private $leaf;

    /**
     * @var string
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private $options;

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
    public function getOptions(): string
    {
        return $this->options;
    }

    /**
     * @param string $options
     */
    public function setOptions(string $options): void
    {
        $this->options = $options;
    }

    /**
     * @return int
     */
    public function getAllegroId(): int
    {
        return $this->allegroId;
    }

    /**
     * @param int $allegroId
     */
    public function setAllegroId(int $allegroId): void
    {
        $this->allegroId = $allegroId;
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
    public function isLeaf(): bool
    {
        return $this->leaf;
    }

    /**
     * @param bool $leaf
     */
    public function setLeaf(bool $leaf): void
    {
        $this->leaf = $leaf;
    }

    /**
     * @return int|null
     */
    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    /**
     * @param int|null $parentId
     */
    public function setParentId(?int $parentId): void
    {
        $this->parentId = $parentId;
    }

}