<?php
namespace local_assign_ai\api;
use aiprovider_datacurso\httpclient\ai_services_api;
use local_assign_ai\utils;

class client {
    public static function send_to_ai($payload) {
        $payload = utils::normalize_payload($payload);

        // $client = new ai_services_api();
        // $response = $client->request('POST', '/forum/chat', $payload);

        // return $response['reply'];

        return [

            'reply' => json_encode($payload, JSON_PRETTY_PRINT),
            'meta' => [
                'provider' => 'mock',
                'model' => 'gpt-5-mini',
                'userid' => $payload['student']['id'] ?? null,
            ]
        ];
    }

}
