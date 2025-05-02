<?php

namespace Page\Analyzer;

use Valitron\Validator;

class UrlValidator
{
    public function validate(array $url): array
    {
        $errors = [];

        if (empty($url['name'])) {
            $errors['name'] = "URL не должен быть пустым";
            return $errors;
        }

        $validator = new Validator(['name' => $url['name']]);
        $validator->rule('url', 'name');
        $validator->rule('lengthMax', 'name', 255);

        /**
         * personal Valitron\Validator validate()
         */
        if (!$validator->validate()) {
            $errors['name'] = 'Некорректный URL';
        }

        return $errors;
    }
}
