<?php

declare(strict_types=1);

namespace App\Support\Scribe\Extracting\Strategies\Responses;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\Response as ScribeResponse;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final class AppDefaultResponses extends Strategy
{
    /**
     * @param  array<string,mixed>  $settings
     * @return array<int,array{
     *     status:int,
     *     description:string,
     *     content?:string,
     *     headers:array<string,string>
     * }>
     */
    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): array
    {
        $responses = [];

        $this->setDescriptionForStatus($endpointData, HttpFoundationResponse::HTTP_OK);
        $this->setDescriptionForStatus($endpointData, HttpFoundationResponse::HTTP_CREATED);
        $this->setDescriptionForStatus($endpointData, HttpFoundationResponse::HTTP_NO_CONTENT);

        if ($endpointData->metadata->authenticated) {
            $responses[] = $this->makeSimpleErrorResponse($endpointData, HttpFoundationResponse::HTTP_UNAUTHORIZED);
        }

        if (array_key_exists('id', $endpointData->urlParameters)) {
            $responses[] = $this->makeSimpleErrorResponse($endpointData, HttpFoundationResponse::HTTP_NOT_FOUND);
        }

        if ($endpointData->bodyParameters || $endpointData->queryParameters) {
            $responses[] = $this->makeValidationErrorResponse($endpointData);
        }

        $responses[] = $this->makeSimpleErrorResponse($endpointData, HttpFoundationResponse::HTTP_TOO_MANY_REQUESTS);
        $responses[] = $this->makeSimpleErrorResponse($endpointData, HttpFoundationResponse::HTTP_SERVICE_UNAVAILABLE);

        return $responses;
    }

    private function setDescriptionForStatus(ExtractedEndpointData $endpointData, int $status): void
    {
        if ($response = $endpointData->responses->firstWhere('status', $status)) {
            /** @var ScribeResponse $response */
            $response->description = HttpFoundationResponse::$statusTexts[$status];
        }
    }

    /**
     * @return array{
     *     status:int,
     *     description:string,
     *     content:string,
     *     headers:array<string,string>
     * }
     */
    private function makeSimpleErrorResponse(ExtractedEndpointData $endpointData, int $status): array
    {
        return [
            'status' => $status,
            'description' => HttpFoundationResponse::$statusTexts[$status],
            'content' => (string) json_encode([
                'title' => HttpFoundationResponse::$statusTexts[$status],
                'status' => $status,
                'detail' => 'string',
                'instance' => '/'.$endpointData->uri,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'headers' => [
                'Content-Type' => 'application/problem+json',
            ],
        ];
    }

    /**
     * @return array{
     *     status:int,
     *     description:string,
     *     content:string,
     *     headers:array<string,string>
     * }
     */
    private function makeValidationErrorResponse(ExtractedEndpointData $endpointData): array
    {
        $response = $this->makeSimpleErrorResponse($endpointData, HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY);
        /** @var array<string, array<string, mixed>> $content */
        $content = json_decode($response['content'], true);

        $parameters = array_merge(
            iterator_to_array($endpointData->queryParameters ?? []),
            iterator_to_array($endpointData->bodyParameters ?? [])
        );

        foreach ($parameters as $parameter) {
            $content['errors'][$parameter->name] = [$parameter->description];
        }

        $response['content'] = (string) json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $response;
    }
}
