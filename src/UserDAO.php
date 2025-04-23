<?php

namespace Page\Analyzer;

class UserDAO {
    private ?\PDO $pdo;

    public function __construct() {
        try {
            $dsn = "pgsql:host=localhost;port=5432;dbname=practice1;";
            $user = "alex";
            $password = "";
            $this->pdo = new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            die($e->getMessage());
        } finally {
            if ($this->pdo) {
                $this->pdo = null;
            }
        }
    }

    public function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (id SERIAL PRIMARY KEY, username TEXT, phone TEXT)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
    }

    public function selectAll(): void
    {
        $sql = "SELECT * FROM users";
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetchAll();
        print_r($result);
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

