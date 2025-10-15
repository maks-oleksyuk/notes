<?php

declare(strict_types=1);

namespace App\Support\Scribe\Writing\OpenApiSpecGenerators;

use Illuminate\Contracts\Config\Repository;
use Knuckles\Camel\Extraction\Metadata;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

final class ScalarOpenApiGenerator extends OpenApiGenerator
{
    public function __construct(
        DocumentationConfig $config,
        private readonly Repository $configRepository,
    ) {
        parent::__construct($config);
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  array<int, array{name: string, description: string, endpoints: array<OutputEndpointData>}>  $groupedEndpoints
     * @return array<string, mixed>
     */
    #[\Override]
    public function root(array $root, array $groupedEndpoints): array
    {
        $tags = [];
        $tagsHashmap = [];

        /** @var string $defaultGroup */
        $defaultGroup = $this->configRepository->get('scribe.groups.default');

        foreach ($groupedEndpoints as $groupedEndpoint) {
            $grouped = [];

            foreach ($groupedEndpoint['endpoints'] as $endpoint) {
                /** @var Metadata $metadata */
                $metadata = $endpoint['metadata'];

                if (empty($metadata->subgroup)) {
                    continue;
                }

                $tagName = $this->generateTagNameFromMetadata($metadata);

                if (in_array($tagName, $tagsHashmap)) {
                    continue;
                }

                $tags[] = [
                    'name' => $tagName,
                    'x-displayName' => $metadata->subgroup,
                    'description' => $metadata->subgroupDescription,
                ];
                $grouped[] = $tagName;
                $tagsHashmap[] = $tagName;
            }

            sort($grouped, SORT_STRING);

            if (! isset($root['x-tagGroups']) || ! is_array($root['x-tagGroups'])) {
                $root['x-tagGroups'] = [];
            }

            $root['x-tagGroups'][] = [
                'name' => $groupedEndpoint['name'],
                'tags' => array_merge($grouped, [
                    // Tag for an endpoint with a group but without a subgroup.
                    $groupedEndpoint['name'].' '.$defaultGroup,
                ]),
            ];
        }

        // set default(_UNGROUPED) tag.
        $tags[] = ['name' => $defaultGroup];
        $root['tags'] = $tags;

        return $root;
    }

    /**
     * @param  array<string, mixed>  $pathItem
     * @return array<string, mixed>
     */
    #[\Override]
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        /** @var Metadata $metadata */
        $metadata = $endpoint['metadata'];

        $pathItem['tags'] = [$this->generateTagNameFromMetadata($metadata)];

        return $pathItem;
    }

    private function generateTagNameFromMetadata(Metadata $metadata): string
    {
        $subgroup = $metadata->subgroup;
        if ($subgroup === null || $subgroup === '') {
            /** @var string $subgroup */
            $subgroup = $this->configRepository->get('scribe.groups.default');
        }

        return mb_trim($metadata->groupName.' '.$subgroup);
    }
}
