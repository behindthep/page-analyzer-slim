<?php

namespace Page\Analyzer;

class Validator
{
    public function validate(array $user): array
    {
        $errors = [];
        if (empty($user['name'])) {
            $errors['name'] = "name Can't be blank";
        }
        if (empty($user['email'])) {
            $errors['email'] = "email Can't be blank";
        }
        if (strlen($user['password']) < 4) {
            $errors['password'] = "password Need to be more 4 charecters";
        }
        if ($user['password'] !== $user['passwordConfirmation']) {
            $errors['password'] = "passwords Need to be equal";
        }
        if (empty($user['city'])) {
            $errors['city'] = "city Can't be blank";
        }

        return $errors;
    }
}
