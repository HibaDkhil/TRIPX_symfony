<?php

namespace App\service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, string $geminiApiKey, string $geminiModel)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $geminiApiKey;
        $this->model = $geminiModel;
    }

    public function chat(string $userMessage, array $context = []): string
{
    $prompt = $this->buildPrompt($userMessage, $context);
    
    // Log for debugging
    error_log('ARIA Prompt: ' . substr($prompt, 0, 500));
    
    $response = $this->httpClient->request('POST', "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
        'json' => [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 500,
            ]
        ]
    ]);

    $data = $response->toArray();
    
    error_log('ARIA Response: ' . json_encode($data));
    
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        throw new \Exception('Invalid response from Gemini API: ' . json_encode($data));
    }
    
    return $data['candidates'][0]['content']['parts'][0]['text'];
}

    private function buildPrompt(string $userMessage, array $context): string
    {
        $userInfo = $context['user'] ?? [];
        $preferences = $context['preferences'] ?? [];
        $destinations = $context['destinations'] ?? [];
        $activities = $context['activities'] ?? [];

        $prompt = "You are Aria, a friendly and enthusiastic AI travel assistant for TripX. ";
        $prompt .= "You help users discover destinations, activities, and accommodations. ";
        $prompt .= "Be warm, use emojis occasionally, and keep responses helpful but concise (2-4 sentences).\n\n";

        if ($userInfo && isset($userInfo['firstName'])) {
            $prompt .= "The user's name is " . $userInfo['firstName'] . ". ";
        }
        
        if ($preferences) {
            $prompt .= "User preferences:\n";
            if (isset($preferences['budgetMinPerNight']) && $preferences['budgetMinPerNight']) {
                $prompt .= "- Budget: $" . $preferences['budgetMinPerNight'] . " - $" . ($preferences['budgetMaxPerNight'] ?? 'unlimited') . " per night\n";
            }
            if (isset($preferences['preferredClimate']) && $preferences['preferredClimate']) {
                $prompt .= "- Preferred climate: " . $preferences['preferredClimate'] . "\n";
            }
            if (isset($preferences['travelPace']) && $preferences['travelPace']) {
                $prompt .= "- Travel pace: " . $preferences['travelPace'] . "\n";
            }
            if (isset($preferences['groupType']) && $preferences['groupType']) {
                $prompt .= "- Traveling: " . $preferences['groupType'] . "\n";
            }
        }

        if (!empty($destinations)) {
            $prompt .= "\nDestinations available in our database (recommend ONLY from these):\n";
            $destNames = [];
            foreach (array_slice($destinations, 0, 15) as $dest) {
                $destNames[] = $dest['name'] . " (" . $dest['country'] . ")";
            }
            $prompt .= implode(", ", $destNames) . "\n";
        }

        $prompt .= "\nIMPORTANT RULES:\n";
        $prompt .= "1. ONLY recommend destinations from the list above\n";
        $prompt .= "2. Be friendly and enthusiastic! Use emojis ✨\n";
        $prompt .= "3. Keep responses short and helpful (2-3 sentences max)\n";
        $prompt .= "4. Address the user by name if you know it\n\n";
        $prompt .= "User message: " . $userMessage . "\n\n";
        $prompt .= "Aria's response:";

        return $prompt;
    }

    public function analyzeImage(string $imageBase64, string $userMessage): string
    {
        $prompt = "You are Aria, a travel assistant. The user uploaded a travel-related image and said: \"$userMessage\". ";
        $prompt .= "Analyze the image and respond helpfully. If it's a travel photo (landmark, beach, mountain, etc.), ";
        $prompt .= "identify it if possible and suggest similar destinations. Be friendly and enthusiastic! Respond in 2-3 sentences.";

        $response = $this->httpClient->request('POST', "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            ['inlineData' => [
                                'mimeType' => 'image/jpeg',
                                'data' => $imageBase64
                            ]]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 500,
                ]
            ]
        ]);

        $data = $response->toArray();
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Invalid response from Gemini API for image');
        }
        
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }
    
}