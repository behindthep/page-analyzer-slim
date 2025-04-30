<?php

namespace Page\Analyzer;

class UrlValidator
{
    public function validate(array $url): array
    {
        $errors = [];
        if (empty($url['name'])) {
            $errors['name'] = "URL не должен быть пустым";
        }

        if (!str_starts_with($url['name'], "https://")) {
            $errors['name'] = "Некорректный URL";
        }

        return $errors;
    }
}
