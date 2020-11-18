<?php

namespace Test\CoSpirit\HAL;

use Guzzle\Tests\GuzzleTestCase;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Http\Exception\ClientErrorResponseException;
use CoSpirit\HAL\RestClient;

class ClientTest extends GuzzleTestCase
{
    public static function setupBeforeClass()
    {
        self::setMockBasePath(__DIR__.'/mock');
    }

    protected function createClient()
    {
        $gclient = new GuzzleClient();
        return new RestClient($gclient, '/');
    }

    public function testGetRelation()
    {
        $client = $this->createClient();
        $this->setMockResponse($client->getGuzzleClient(), 'index.hal-response');

        $this->assertEquals('http://api.dev/test/{id}', $client->getRelation('test'));
        $this->assertEquals('http://api.dev/test/{id}', $client->getRelation('test'));
        // tests that the relations are cached
        $this->assertCount(1, $this->getMockedRequests());

        $this->setExpectedException('InvalidArgumentException');
        $client->getRelation('unknown');
    }

    public function testQuery()
    {
        $client = $this->createClient();
        $mock = $this->setMockResponse($client->getGuzzleClient(), 'index.hal-response');
        $mock->addResponse($this->getMockResponse('test.query.hal-response'));
        $mock->addResponse($this->getMockResponse('test.query.json-response'));

        $data = $client->query('test', ['id' => 12]);
        $this->assertInstanceOf('CoSpirit\Hal\Navigator', $data);
        $this->assertEquals(1, $data->id);

        // tests the templated uri used in the request
        $requests = $this->getMockedRequests();
        $r = end($requests);
        $this->assertEquals('GET', $r->getMethod());
        $this->assertEquals('http://api.dev/test/12', $r->getUrl());

        // tests simple json response
        $data = $client->query('test');
        $this->assertEquals('john', $data['name']);
    }

    public function testQueryError()
    {
        $client = $this->createClient();
        $mock = $this->setMockResponse($client->getGuzzleClient(), 'index.hal-response');
        $mock->addResponse($this->getMockResponse('400.response'));

        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');

        $client->query('test');
    }

    public function testCommand()
    {
        $client = $this->createClient();
        $mock = $this->setMockResponse($client->getGuzzleClient(), 'index.hal-response');
        $mock->addResponse($this->getMockResponse('test.query.hal-response'));

        $data = $client->command('test', [
            'id' => 3,
            'name' => 'Rupert',
        ]);

        $this->assertInstanceOf('CoSpirit\Hal\Navigator', $data);

        $requests = $this->getMockedRequests();
        $r = end($requests);
        $this->assertEquals('POST', $r->getMethod());
        $this->assertEquals('application/json', $r->getHeader('Content-Type'));
        $this->assertEquals('http://api.dev/test/3', $r->getUrl());

        $body = json_decode((string) $r->getBody(), true);
        $this->assertCount(1, $body);
        $this->assertEquals('Rupert', $body['name']);
    }

    public function testCommandError()
    {
        $client = $this->createClient();
        $mock = $this->setMockResponse($client->getGuzzleClient(), 'index.hal-response');
        $mock->addResponse($this->getMockResponse('400.response'));

        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');

        $client->command('test');
    }

    public function testCommandUploadFile()
    {
        $client = $this->createClient();
        $mock = $this->setMockResponse($client->getGuzzleClient(), 'index.hal-response');
        $mock->addResponse($this->getMockResponse('204.response'));

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

        $requests = $this->getMockedRequests();
        $r = end($requests);
        $this->assertEquals('POST', $r->getMethod());
        $this->assertEquals('multipart/form-data', $r->getHeader('Content-Type'));
        $this->assertEquals('http://api.dev/test/3', $r->getUrl());

        $this->assertEquals('Rupert', $r->getPostField('name'));
        $this->assertNotEmpty($r->getPostFile('file'));
    }
}
