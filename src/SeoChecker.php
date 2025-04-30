<?php

namespace Page\Analyzer;

class SeoChecker
{
    public function check(string $url): array
    {
        // Выполняем HTTP-запрос к URL
        $response = @file_get_contents($url);
        $responseCode = $http_response_header[0] ?? 'HTTP/1.1 404 Not Found';

        preg_match('/HTTP\/\d\.\d (\d+)/', $responseCode, $matches);
        $code = $matches[1] ?? 404;

        // Извлечение метаданных
        $h1 = $this->extractH1($response);
        $title = $this->extractTitle($response);
        $description = $this->extractDescription($response);

        return [
            'response_code' => $code,
            'header' => $h1,
            'title' => $title,
            'description' => $description,
        ];
    }

    private function extractH1(string $html): string
    {
        preg_match('/<h1>(.*?)<\/h1>/', $html, $matches);
        return $matches[1] ?? '';
    }

    private function extractTitle(string $html): string
    {
        preg_match('/<title>(.*?)<\/title>/', $html, $matches);
        return $matches[1] ?? '';
    }

    private function extractDescription(string $html): string
    {
        preg_match('/<meta name="description" content="(.*?)"/', $html, $matches);
        return $matches[1] ?? '';
    }
}
