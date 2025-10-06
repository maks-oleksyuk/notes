<?php

declare(strict_types=1);

namespace App\Support\Scribe\Extracting\Strategies\Responses;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\Response as ScribeResponse;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Symfony\Component\HttpFoundation\Response;

final class AppDefaultResponses extends Strategy
{
    /**
     * @param  array<string,mixed>  $settings
     * @return array<int,array{status:int,description:string,content?:string}>
     */
    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): array
    {
        $responses = [];

        $this->setDescriptionForStatus($endpointData, Response::HTTP_OK);
        $this->setDescriptionForStatus($endpointData, Response::HTTP_CREATED);
        $this->setDescriptionForStatus($endpointData, Response::HTTP_NO_CONTENT);

        if ($endpointData->metadata->authenticated) {
            $responses[] = $this->makeSimpleErrorResponse($endpointData, Response::HTTP_UNAUTHORIZED);
        }

        if (array_key_exists('id', $endpointData->urlParameters)) {
            $responses[] = $this->makeSimpleErrorResponse($endpointData, Response::HTTP_NOT_FOUND);
        }

        if ($endpointData->bodyParameters || $endpointData->queryParameters) {
            $responses[] = $this->makeSimpleErrorResponse($endpointData, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $responses[] = $this->makeSimpleErrorResponse($endpointData, Response::HTTP_TOO_MANY_REQUESTS);
        $responses[] = $this->makeSimpleErrorResponse($endpointData, Response::HTTP_SERVICE_UNAVAILABLE);

        return $responses;
    }

    private function setDescriptionForStatus(ExtractedEndpointData $endpointData, int $status): void
    {
        $collection = $endpointData->responses->where('status', $status);

        foreach ($collection as $response) {
            /** @var ScribeResponse $response */
            $response->description = Response::$statusTexts[$status];
        }
    }

    /**
     * @return array{status:int,description:string,content:string}
     */
    private function makeSimpleErrorResponse(ExtractedEndpointData $endpointData, int $status): array
    {
        return [
            'status' => $status,
            'description' => Response::$statusTexts[$status],
            'content' => (string) json_encode([
                'title' => Response::$statusTexts[$status],
                'status' => $status,
                'detail' => 'string',
                'instance' => '/'.$endpointData->uri,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'headers' => [
                'Content-Type' => 'application/problem+json',
            ],
        ];
    }
}
