<?php

namespace Page\Analyzer;

class Check
{
    private ?int $id = null;
    private ?int $responseCode = null;
    private ?string $header = null;
    private ?string $title = null;
    private ?string $description = null;
    private ?string $createdAt = null;

    public static function fromArray(array $checkData): Check
    {
        [$responseCode, $header, $title, $description, $createdAt] = $checkData;
        $check = new Check();
        $check->setResponseCode($responseCode);
        $check->setHeader($header);
        $check->setTitle($title);
        $check->setDescription($description);
        $check->setCreatedAt($createdAt);

        return $check;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function getHeader(): ?string
    {
        return $this->header;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setResponseCode(int $responseCode): void
    {
        $this->responseCode = $responseCode;
    }

    public function setHeader(string $header): void
    {
        $this->header = $header;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
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
