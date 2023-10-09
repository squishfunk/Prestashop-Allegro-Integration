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
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $allegroName;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
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
    public function getAllegroName(): string
    {
        return $this->allegroName;
    }

    /**
     * @param string $allegroName
     */
    public function setAllegroName(string $allegroName): void
    {
        $this->allegroName = $allegroName;
    }

    /**
     * @return int
     */
    public function getParentId(): int
    {
        return $this->parentId;
    }

    /**
     * @param int $parentId
     */
    public function setParentId(int $parentId): void
    {
        $this->parentId = $parentId;
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

}