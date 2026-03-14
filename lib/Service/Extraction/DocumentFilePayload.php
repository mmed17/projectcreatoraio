<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service\Extraction;

final class DocumentFilePayload
{
    public function __construct(
        private readonly string $fileName,
        private readonly string $mimeType,
        private readonly string $content,
    ) {
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
