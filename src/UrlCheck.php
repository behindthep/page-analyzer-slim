<?php

namespace Page\Analyzer;

class UrlCheck
{
    private ?int $id = null;
    private ?string $name = null;
    private ?string $lastCheck = null;
    private ?int $responseCode = null;

    public function __construct(int $id, string $name, ?string $lastCheck = null, ?int $responseCode = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->lastCheck = $lastCheck;
        $this->responseCode = $responseCode;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLastCheck(): ?string
    {
        return $this->lastCheck;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }
}
