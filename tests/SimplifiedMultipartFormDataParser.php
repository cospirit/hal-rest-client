<?php

declare(strict_types=1);

namespace Test\CoSpirit\HAL;

use RuntimeException;

/**
 * For testing purposes only
 * Covers only basic cases with files and fields containing single value
 * Works only if the format lines separated by \r\n
 */
final class SimplifiedMultipartFormDataParser
{
    /** @var string[] */
    private array $lines;

    private string $boundary;

    public function __construct(string $body)
    {
        $this->lines = explode("\r\n", $body);
        $this->boundary = $this->parseBoundary();
    }

    /**
     * @return array
     * @throws RuntimeException
     */
    public function parse(): array
    {
        $files = [];
        $fields = [];

        if (!$this->lines) {
            return compact('files', 'fields');
        }

        while ($this->skipToNextBoundary()) {
            $headers = $this->parseNextHeaders();
            if (!isset($headers['content-disposition'])) {
                throw new RuntimeException('Missing Content-Disposition header');
            }

            $contentDisposition = $this->parseContentDisposition($headers['content-disposition']);
            if ($this->isFile($contentDisposition)) {
                $files[$contentDisposition['name']] = [
                    'filename' => $contentDisposition['filename'],
                    'content' => $this->parseNextBody(),
                ];
            } elseif ($this->isField($contentDisposition)) {
                $fields[$contentDisposition['name']] = $this->parseNextBody();
            } else {
                throw new RuntimeException('Wrong Content-Disposition header');
            }
        }

        return compact('files', 'fields');
    }

    private function parseNextHeaders(): array
    {
        $headers = [];
        while ($line = trim(array_shift($this->lines))) {
            [$header, $value] = explode(':', $line, 2);
            $headers[strtolower($header)] = $value;
        }

        return $headers;
    }

    private function parseNextBody(): string
    {
        $bodyLines = [];
        while (!$this->isBoundary(reset($this->lines))) {
            $line = array_shift($this->lines);
            $bodyLines[] = $line;
        }

        return implode("\r\n", $bodyLines);
    }

    private function skipToNextBoundary(): bool
    {
        $line = array_shift($this->lines);
        if ($line === null) {
            throw new RuntimeException('Unexpected end of form data');
        }

        if ($this->isBoundaryStart($line)) {
            return true;
        }

        if ($this->isEndOfFormData($line)) {
            return false;
        }

        throw new RuntimeException("Expected boundary. Got \"$line\"");
    }

    private function isBoundary(string $line): bool
    {
        return $this->isBoundaryStart($line)
            || $this->isEndOfFormData($line);
    }

    private function isBoundaryStart(string $line): bool
    {
        return $line === '--'.$this->boundary;
    }

    private function isEndOfFormData(string $line): bool
    {
        return $line === '--'.$this->boundary.'--';
    }

    private function parseBoundary(): string
    {
        $line = reset($this->lines);

        if (!str_starts_with($line, '--')) {
            throw new RuntimeException('Wrong boundary format');
        }

        return substr($line, 2);
    }

    /**
     * @param array<string, string> $contentDisposition
     */
    private function isFile(array $contentDisposition): bool
    {
        return isset($contentDisposition['name'], $contentDisposition['filename']);
    }

    /**
     * @param array<string, string> $contentDisposition
     */
    private function isField(array $contentDisposition): bool
    {
        return isset($contentDisposition['name']);
    }

    /**
     * @return array<string, string>
     */
    private function parseContentDisposition(string $contentDispositionString): array
    {
        $contentDisposition = [];
        foreach(array_map(static fn(string $part) => trim($part), explode(';', $contentDispositionString)) as $part) {
            if (strpos($part, '=')) {
                [$name, $value] = explode('=', $part);
                if (preg_match('/"(.*)"/', $value, $matches)) {
                    $value = $matches[1];
                }
                $contentDisposition[$name] = $value;
            } else {
                $contentDisposition[] = $part;
            }
        }

        return $contentDisposition;
    }
}
