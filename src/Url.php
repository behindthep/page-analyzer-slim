<?php

namespace Page\Analyzer;

// Абстракция над строками БД
class Url
{
    private ?int $id = null;
    private ?string $name = null;
    private ?string $createdAt = null;

    public static function fromArray(array $urlData): Url
    {
        [$name, $createdAt] = $urlData;
        $url = new Url();
        $url->setName($name);
        $url->setCreatedAt($createdAt);
        return $url;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setCreatedAt(string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function exists(): bool
    {
        return !is_null($this->getId());
    }
}
