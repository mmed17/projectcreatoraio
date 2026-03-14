<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service\Extraction;

final class DocumentExtractionResult
{
    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(
        private readonly array $fields,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
