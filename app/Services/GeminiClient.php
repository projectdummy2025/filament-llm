<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GeminiClient
{
    /**
     * @throws RequestException
     */
    public function generateText(string $prompt, int $maxOutputTokens = 1024): string
    {
        $apiKey = (string) config('services.gemini.key');
        $model = (string) config('services.gemini.model');

        if ($apiKey === '' || $apiKey === '...') {
            throw new \RuntimeException('Missing GEMINI_API_KEY.');
        }

        if ($model === '') {
            throw new \RuntimeException('Missing GEMINI_MODEL.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::timeout(60)
            ->retry(2, 500)
            ->withQueryParameters(['key' => $apiKey])
            ->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'maxOutputTokens' => $maxOutputTokens,
                ],
            ])
            ->throw();

        $data = $response->json();
        $text = data_get($data, 'candidates.0.content.parts.0.text');

        if (!is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Gemini response did not contain text.');
        }

        return trim($text);
    }
}
