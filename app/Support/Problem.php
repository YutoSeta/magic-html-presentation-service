<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class Problem
{
    /** @param array<string,array<int,string>> $errors */
    public static function response(Request $request, int $status, string $type, string $message, array $errors = []): JsonResponse
    {
        ksort($errors, SORT_STRING);

        $body = [
            'contract_version' => '1.0',
            'type' => $type,
            'message' => $message,
            'errors' => array_map(
                static fn (string $field, array $messages): array => [
                    'field' => $field,
                    'messages' => array_values($messages),
                ],
                array_keys($errors),
                array_values($errors),
            ),
        ];

        return response()->json($body, $status);
    }
}
