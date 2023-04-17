<?php

namespace Test\CoSpirit\HAL;

use CoSpirit\HAL\Navigator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client as GuzzleClient;
use CoSpirit\HAL\RestClient;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RestClientTest extends TestCase
{
    protected static string $mockBasePath;

    private function getResponseBody(string $path): string
    {
        $path = self::$mockBasePath . DIRECTORY_SEPARATOR . $path;

        if (!file_exists($path)) {
            throw new InvalidArgumentException('Unable to open mock file: ' . $path);
        }

        return file_get_contents($path);
    }

    public static function setupBeforeClass(): void
    {
        self::$mockBasePath = __DIR__.'/mock';
    }

    private function createClientWithHandler(callable $handler): RestClient
    {
        return new RestClient(
            new GuzzleClient([
                'handler' => HandlerStack::create($handler),
            ]),
            '/'
        );
    }

    public function testGetRelation(): void
    {
        $mockHandler = new MockHandler([
            $this->createIndexHalResponse(),
        ]);

        $client = $this->createClientWithHandler($mockHandler);

        $this->assertEquals('http://api.dev/test/{id}', $client->getRelation('test'));
        $this->assertEquals('http://api.dev/test/{id}', $client->getRelation('test'));
        $this->assertNoMockResponsesLeft($mockHandler);

        $this->expectException(InvalidArgumentException::class);
        $client->getRelation('unknown');
    }

    public function testQuery(): void
    {
        $mockHandler = new MockHandler([
            $this->createIndexHalResponse(),
            $this->createQueryHalResponse(),
            new Response(200, [
                'Allow' => 'GET',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'Content-Type' => 'application/json',
                'Date' => 'Mon, 20 Jul 2015 12:32:37 GMT',
                'Server' => 'nginx/1.8.0',
                'Transfer-Encoding' => 'chunked',
            ], $this->getResponseBody('test.query.json-response')),
        ]);

        $client = $this->createClientWithHandler($mockHandler);

        $data = $client->query('test', ['id' => 12]);
        $this->assertInstanceOf(Navigator::class, $data);
        $this->assertEquals(1, $data->id);

        // tests the templated uri used in the request
        $lastRequest = $mockHandler->getLastRequest();
        $this->assertEquals('GET', $lastRequest->getMethod());
        $this->assertEquals('http://api.dev/test/12', $lastRequest->getUri());

        // tests simple json response
        $data = $client->query('test');
        $this->assertEquals('john', $data['name']);
    }

    public function testQueryError(): void
    {
        $mockHandler = new MockHandler([
            $this->createIndexHalResponse(),
            $this->createBadRequestResponse(),
        ]);

        $client = $this->createClientWithHandler($mockHandler);

        $this->expectException(GuzzleException::class);

        $client->query('test');
    }

    public function testCommand(): void
    {
        $mockHandler = new MockHandler([
            $this->createIndexHalResponse(),
            $this->createQueryHalResponse(),
        ]);

        $client = $this->createClientWithHandler($mockHandler);

        $data = $client->command('test', [
            'id' => 3,
            'name' => 'Rupert',
        ]);

        $this->assertInstanceOf('CoSpirit\Hal\Navigator', $data);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertEquals('POST', $lastRequest->getMethod());
        $this->assertEquals('application/json', $lastRequest->getHeader('Content-Type')[0]);
        $this->assertEquals('http://api.dev/test/3', $lastRequest->getUri());

        $body = json_decode($lastRequest->getBody()->getContents(), true);
        $this->assertCount(1, $body);
        $this->assertEquals('Rupert', $body['name']);
    }

    public function testCommandError(): void
    {
        $mockHandler = new MockHandler([
            $this->createIndexHalResponse(),
            $this->createBadRequestResponse(),
        ]);

        $client = $this->createClientWithHandler($mockHandler);

        $this->expectException(GuzzleException::class);

        $client->command('test');
    }

    public function testCommandUploadFile(): void
    {
        $mockHandler = new MockHandler([
            $this->createIndexHalResponse(),
            $this->createNoContentResponse(),
        ]);

        $client = $this->createClientWithHandler($mockHandler);

        $data = $client->command('test',
            [
                'id' => 3,
                'name' => 'Rupert',
            ],
            [
                'file' => __DIR__.'/mock/myfile.txt'
            ]
        );

        $this->assertEmpty($data);

        $lastRequest = $mockHandler->getLastRequest();
        $this->assertEquals('POST', $lastRequest->getMethod());
        $this->assertStringContainsString('multipart/form-data', $lastRequest->getHeader('Content-Type')[0]);
        $this->assertEquals('http://api.dev/test/3', $lastRequest->getUri());

        $multipartData = (new SimplifiedMultipartFormDataParser($lastRequest->getBody()->getContents()))->parse();

        $this->assertEquals('Rupert', $multipartData['fields']['name']);
        $this->assertArrayHasKey('file', $multipartData['files']);
        $this->assertNotEmpty($multipartData['files']['file']);
    }

    private function createNoContentResponse(): Response
    {
        return new Response(204, [
            'Allow' => 'POST',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Content-Type' => 'application/json',
            'Date' => 'Mon, 20 Jul 2015 12:32:37 GMT',
            'Server' => 'nginx/1.8.0',
        ]);
    }

    private function createIndexHalResponse(): Response
    {
        return new Response(200, [
            'Allow' => 'GET',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Content-Type' => 'application/hal+json',
            'Date' => 'Mon, 20 Jul 2015 12:32:37 GMT',
            'Server' => 'nginx/1.8.0',
            'Transfer-Encoding' => 'chunked',
        ], $this->getResponseBody('index.hal-response'));
    }

    private function createBadRequestResponse(): Response
    {
        return new Response(400, [
            'Allow' => 'GET,POST',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Content-Type' => 'application/hal+json',
            'Date' => 'Mon, 20 Jul 2015 12:32:37 GMT',
            'Server' => 'nginx/1.8.0',
            'Transfer-Encoding' => 'chunked',
        ], $this->getResponseBody('400.response'));
    }

    private function createQueryHalResponse(): Response
    {
        return new Response(200, [
            'Allow' => 'GET',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Content-Type' => 'application/hal+json',
            'Date' => 'Mon, 20 Jul 2015 12:32:37 GMT',
            'Server' => 'nginx/1.8.0',
            'Transfer-Encoding' => 'chunked',
        ], $this->getResponseBody('test.query.hal-response'));
    }

    private function assertNoMockResponsesLeft(MockHandler $mockHandler): void
    {
        $this->assertCount(0, $mockHandler, 'There should not be any mock responses left in the queue');
    }
}
