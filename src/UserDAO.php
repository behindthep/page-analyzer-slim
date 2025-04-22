<?php

namespace Page\Analyzer;

class UserDAO {
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function save(User $user): void
    {
        if (is_null($user->getId())) {
            $sql = "INSERT INTO users (username, phone) VALUES (?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $username = $user->getUsername();
            $phone = $user->getPhone();
            $stmt->bindParam(1, $username);
            $stmt->bindParam(2, $phone);
            $stmt->execute();

            $id = (int) $this->pdo->lastInsertId();
            $user->setId($id);
        } else {
            $sql = "UPDATE users SET username = ?, phone = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $username = $user->getUsername();
            $phone = $user->getPhone();
            $id = $user->getId();
            $stmt->bindParam(1, $username);
            $stmt->bindParam(2, $phone);
            $stmt->bindParam(3, $id);
            $stmt->execute();
        }
    }

    public function find(int $id): ?User
    {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        if ($result) {
            $user = new User($result['username'], $result['phone']);
            $user->setId($id);
            return $user;
        }
        return null;
    }

    public function delete(User $user): void
    {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $id = $user->getId();
        $stmt->execute([$id]);
    }
}

