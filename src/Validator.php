<?php

namespace Page\Analyzer;

class Validator
{
    public function validate(array $user): array
    {
        $errors = [];

        if (empty($user['name'])) {
            $errors['name'] = "Name can't be blank.";
        } elseif (strlen($user['name']) < 4) {
            $errors['name'] = "Name must be greater than 4 characters.";
        }

        if (empty($user['email'])) {
            $errors['email'] = "Email can't be blank.";
        }

        if (empty($user['password'])) {
            $errors['password'] = "Password can't be blank.";
        } elseif (strlen($user['password']) < 4) {
            $errors['password'] = "Password must be greater than 4 characters.";
        }

        if (empty($user['passwordConfirmation'])) {
            $errors['passwordConfirmation'] = "Password confirmation can't be blank.";
        } elseif ($user['password'] !== $user['passwordConfirmation']) {
            $errors['password'] = "Passwords need to be equal.";
            $errors['passwordConfirmation'] = "Passwords need to be equal.";
        }

        if (empty($user['city'])) {
            $errors['city'] = "City can't be blank.";
        }

        return $errors;
    }
}
