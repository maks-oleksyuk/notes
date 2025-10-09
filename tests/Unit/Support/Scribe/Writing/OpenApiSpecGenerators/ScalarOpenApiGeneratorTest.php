<?php

declare(strict_types=1);

use App\Support\Scribe\Writing\OpenApiSpecGenerators\ScalarOpenApiGenerator;
use Illuminate\Contracts\Config\Repository;
use Knuckles\Camel\Extraction\Metadata;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Symfony\Component\HttpFoundation\Request;

beforeEach(function (): void {
    $this->configRepository = Mockery::mock(Repository::class);
    $this->configRepository
        ->shouldReceive('get')
        ->with('scribe.groups.default')
        ->andReturn('_UNGROUPED');

    $this->generator = new ScalarOpenApiGenerator(
        new DocumentationConfig,
        $this->configRepository
    );
});

it('adds tags and x-tagGroups for grouped endpoints with subgroups', function (): void {
    $endpoint1 = new OutputEndpointData([
        'uri' => '/auth',
        'httpMethods' => [Request::METHOD_GET],
        'metadata' => new Metadata([
            'groupName' => 'Users',
            'subgroup' => 'Authentication',
            'subgroupDescription' => 'Endpoints related to auth',
        ]),
    ]);
    $endpoint2 = new OutputEndpointData([
        'uri' => '/profiles',
        'httpMethods' => [Request::METHOD_GET],
        'metadata' => new Metadata([
            'groupName' => 'Users',
            'subgroup' => 'Profiles',
            'subgroupDescription' => 'User profile management',
        ]),
    ]);

    $groupedEndpoints = [[
        'name' => 'Users',
        'description' => 'User operations',
        'endpoints' => [$endpoint1, $endpoint2],
    ]];

    $root = $this->generator->root([], $groupedEndpoints);

    expect($root)
        ->toHaveKey('tags')
        ->and($root['tags'])
        ->toContain([
            'name' => 'Users Authentication',
            'x-displayName' => 'Authentication',
            'description' => 'Endpoints related to auth',
        ], [
            'name' => 'Users Profiles',
            'x-displayName' => 'Profiles',
            'description' => 'User profile management',
        ], [
            'name' => '_UNGROUPED',
        ])
        ->and($root['x-tagGroups'][0])
        ->toHaveKeys(['name', 'tags'])
        ->and($root['x-tagGroups'][0]['name'])->toBe('Users')
        ->and($root['x-tagGroups'][0]['tags'])
        ->toContain('Users Authentication', 'Users Profiles', 'Users _UNGROUPED');

});

it('skips endpoints without subgroup', function (): void {
    $endpoint = new OutputEndpointData([
        'uri' => '/users',
        'httpMethods' => [Request::METHOD_GET],
        'metadata' => new Metadata([
            'groupName' => 'Users',
        ]),
    ]);

    $groupedEndpoints = [[
        'name' => 'Users',
        'description' => 'User operations',
        'endpoints' => [$endpoint],
    ]];

    $root = $this->generator->root([], $groupedEndpoints);

    expect($root['tags'])->toContain(['name' => '_UNGROUPED'])
        ->and($root['x-tagGroups'][0]['tags'])->toContain('Users _UNGROUPED');
});

it('does not duplicate tags if the same subgroup appears multiple times', function (): void {
    $metadata = new Metadata([
        'groupName' => 'Users',
        'subgroup' => 'Authentication',
        'subgroupDescription' => 'Duplicate test',
    ]);

    $endpoint1 = new OutputEndpointData([
        'uri' => '/auth/login',
        'httpMethods' => [Request::METHOD_GET],
        'metadata' => $metadata,
    ]);
    $endpoint2 = new OutputEndpointData([
        'uri' => '/auth/register',
        'httpMethods' => [Request::METHOD_GET],
        'metadata' => $metadata,
    ]);

    $groupedEndpoints = [[
        'name' => 'Users',
        'endpoints' => [$endpoint1, $endpoint2],
    ]];

    $root = $this->generator->root([], $groupedEndpoints);

    $names = array_column($root['tags'], 'name');
    $count = array_count_values($names);

    expect($count['Users Authentication'])->toBe(1);
});

it('sets default subgroup when subgroup is null or empty', function (): void {
    $metadata = new Metadata([
        'groupName' => 'Users',
        'subgroup' => null,
    ]);

    $method = new ReflectionMethod($this->generator, 'generateTagNameFromMetadata');

    $tagName = $method->invoke($this->generator, $metadata);

    expect($tagName)->toBe('Users _UNGROUPED');
});

it('adds tags for pathItem method', function (): void {
    $endpoint = new OutputEndpointData([
        'uri' => '/settings',
        'httpMethods' => [Request::METHOD_GET],
        'metadata' => new Metadata([
            'groupName' => 'Users',
            'subgroup' => 'Settings',
        ]),
    ]);

    $pathItem = $this->generator->pathItem([], [], $endpoint);

    expect($pathItem['tags'])->toBe(['Users Settings']);
});
