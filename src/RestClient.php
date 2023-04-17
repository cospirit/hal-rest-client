<?php

namespace CoSpirit\HAL;

use GuzzleHttp\Client as Guzzle;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Rize\UriTemplate;

class RestClient
{
    protected ?array $cachedRelations = null;

    protected ?UriTemplate $uriTemplater;

    public function __construct(
        protected Guzzle $guzzleClient,
        protected string $indexUrl
    ) {
        $this->uriTemplater = new UriTemplate();
    }

    /**
     * @throws GuzzleException
     * @throws \InvalidArgumentException
     * @throws JsonException
     *
     * @return string
     */
    public function getRelation(string $relation, bool $isPublic = false)
    {
        if (!isset($this->cachedRelations)) {
            $url = $this->indexUrl.($isPublic ? 'public/' : '');

            $result = $this->jsonDecode($this->guzzleClient->get($url));
            $this->cachedRelations = $result['_links'];
        }

        if (!array_key_exists($relation, $this->cachedRelations)) {
            throw new \InvalidArgumentException(sprintf('Relation %s not found', $relation));
        }

        return $this->cachedRelations[$relation]['href'];
    }

    /**
     * Render a templated uri with the given parameters
     *
     * @param array<string, mixed> $parameters
     *
     * @return string
     */
    protected function renderUri(string $templatedUri, array $parameters = [])
    {
        return $this->uriTemplater->expand($templatedUri, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     * @throws GuzzleException
     * @throws JsonException
     * @return Navigator|array|string|int|bool|float
     */
    public function query(string $relation, array $parameters = [], bool $isPublic = false)
    {
        $templatedUrl = $this->getRelation($relation, $isPublic);
        $url = $this->renderUri($templatedUrl, $parameters);

        $query = $this->guzzleClient->get($url);

        $jsonData = $this->jsonDecode($query);

        if ('application/hal+json' === $this->getContentType($query)) {
            return new Navigator($jsonData);
        }

        return $jsonData;
    }

    /**
     * @param array<string, mixed> $parameters
     * @throws GuzzleException
     */
    public function getFile(string $relation, array $parameters = []): string
    {
        $templatedUrl = $this->getRelation($relation);
        $url = $this->renderUri($templatedUrl, $parameters);

        $query = $this->guzzleClient->get($url);

        return $query->getBody()->getContents();
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, string> $files
     * @throws GuzzleException
     * @return Navigator|mixed
     */
    public function command(string $relation, array $parameters = [], array $files = [])
    {
        $templatedUrl = $this->getRelation($relation);

        // expand template url + remove templated params from parameters
        $url = $this->renderUri($templatedUrl, $parameters);
        $templatedParameters = $this->uriTemplater->extract($templatedUrl, $url);
        $parameters = array_diff_key($parameters, $templatedParameters);

        $options['headers'] = [];

        if ($files) {
            $options['multipart'] = [];
            foreach ($files as $name => $file) {
                $options['multipart'][] = [
                    'name' => $name,
                    'contents' => Utils::tryFopen($file, 'r'),
                    'filename' => basename($file),
                ];
            }
            foreach ($parameters as $name => $value) {
                $options['multipart'][] = [
                    'name' => $name,
                    'contents' => $value,
                ];
            }
        } else {
            $options['headers'] = ['Content-Type' => 'application/json'];
            $options['json'] = $parameters;
        }

        $command = $this->guzzleClient->post($url, $options);

        switch ($this->getContentType($command)) {
            case 'application/hal+json':
                $response = new Navigator($this->jsonDecode($command));
                break;
            case 'application/json':
                $response = $this->jsonDecode($command);
                break;
            default:
                $response = $command->getBody()->getContents();
                break;
        }

        return $response;
    }

    private function getContentType(ResponseInterface $response): ?string
    {
        $contentType = $response->getHeader('Content-Type');
        return reset($contentType);
    }

    /**
     * @param ResponseInterface $response
     * @return mixed
     * @throws \JsonException
     */
    private function jsonDecode(ResponseInterface $response): mixed
    {
        return json_decode($response->getBody()->getContents(),true);
    }
}
