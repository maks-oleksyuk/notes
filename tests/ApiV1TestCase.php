<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base test case for API v1 endpoints.
 *
 * Automatically verifies API version headers on all JSON responses.
 */
abstract class ApiV1TestCase extends TestCase
{
    protected string $apiVersionStatus = 'active';

    /**
     * @param  array<string, mixed>  $headers
     * @return TestResponse<Response>
     */
    #[\Override]
    public function getJson($uri, array $headers = [], $options = 0): TestResponse
    {
        $response = parent::getJson($uri, $headers, $options);

        return $this->verifyApiVersionHeaders($response);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $headers
     * @return TestResponse<Response>
     */
    #[\Override]
    public function postJson($uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        $response = parent::postJson($uri, $data, $headers, $options);

        return $this->verifyApiVersionHeaders($response);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $headers
     * @return TestResponse<Response>
     */
    #[\Override]
    public function putJson($uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        $response = parent::putJson($uri, $data, $headers, $options);

        return $this->verifyApiVersionHeaders($response);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $headers
     * @return TestResponse<Response>
     */
    #[\Override]
    public function patchJson($uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        $response = parent::patchJson($uri, $data, $headers, $options);

        return $this->verifyApiVersionHeaders($response);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $headers
     * @return TestResponse<Response>
     */
    #[\Override]
    public function deleteJson($uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        $response = parent::deleteJson($uri, $data, $headers, $options);

        return $this->verifyApiVersionHeaders($response);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return TestResponse<Response>
     */
    protected function verifyApiVersionHeaders(TestResponse $response): TestResponse
    {
        return assertApiVersionHeaders($response, $this->apiVersionStatus);
    }

    /**
     * Skip API version header verification for a specific test.
     * Use this when testing error responses or non-API endpoints.
     */
    protected function skipApiVersionVerification(): void
    {
        $this->apiVersionStatus = '';
    }
}
