<?php

namespace Page\Analyzer;

class UrlCheck
{
    private ?int $id = null;
    private ?string $name = null;
    private ?string $lastCheck = null;
    private ?int $responseCode = null;

    public function __construct($id, $name, $lastCheck = null, $responseCode = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->lastCheck = $lastCheck;
        $this->responseCode = $responseCode;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLastCheck()
    {
        return $this->lastCheck;
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }
}
