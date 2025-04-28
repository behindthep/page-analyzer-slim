<?php

namespace Page\Analyzer;

class User
{
    public function __construct(
        private string $username,
        private string $phone,
        private ?int $id = null
    ) {
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
