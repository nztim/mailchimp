<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use NZTim\Mailchimp\GuzzleFactory;
use NZTim\Mailchimp\Mailchimp;
use NZTim\Mailchimp\MailchimpException;
use GuzzleHttp\Psr7\Response;

class MailchimpTest extends PHPUnit_Framework_TestCase
{
    protected $apikey = 'testkey';
    protected $dc = 'testdc';
    protected $guzzleFactory;
    protected $guzzle;
    protected $mc;
    protected $response;

    public function testIsInstantiable()
    {
        $this->assertTrue(class_exists(Mailchimp::class));
    }

    public function setUp()
    {
        $this->guzzleFactory = Mockery::mock(GuzzleFactory::class);
        $this->guzzle = Mockery::mock(Client::class);
        $this->guzzleFactory->shouldReceive('createClient')->andReturn($this->guzzle);
        $this->mc = new Mailchimp($this->apikey, $this->dc, $this->guzzleFactory);
        $this->response = Mockery::mock(Response::class);
    }

    /**
     * @expectedException NZTim\Mailchimp\MailchimpException
     * @expectedExceptionMessage Mailchimp networking error: []
     */
    public function testCallApiGetException()
    {
        $requestException = Mockery::mock(RequestException::class);
        $requestException->shouldReceive('getMessage')->andReturn('get failure');
        $requestException->shouldReceive('getRequest')->andReturn(Mockery::self());
        $requestException->shouldReceive('getHeaders')->andReturn([]);
        $this->guzzle->shouldReceive('get')->with("lists/list-1")->andThrow($requestException);
        $this->mc->checkStatus('list-1', 'test@test.com');
    }

    /**
     * @expectedException NZTim\Mailchimp\MailchimpException
     * @expectedExceptionMessage Unknown response: 999 testing
     */
    public function testCheckListExistsUnknownResponse()
    {
        $this->response->shouldReceive('getStatusCode')->andReturn(999);
        $this->response->shouldReceive('getReasonPhrase')->andReturn('testing');
        $this->guzzle->shouldReceive('get')->with("lists/list-1")->andReturn($this->response);
        $this->mc->checkStatus('list-1', 'test@test.com');
    }

    /**
     * @expectedException NZTim\Mailchimp\MailchimpException
     * @expectedExceptionMessage List ID:list-1 does not exist
     */
    public function testCheckListExistsFalse()
    {
        $this->response->shouldReceive('getStatusCode')->andReturn(404);
        $this->guzzle->shouldReceive('get')->with("lists/list-1")->andReturn($this->response);
        $this->mc->checkStatus('list-1', 'test@test.com');
    }

    public function testCheckStatusNotFound()
    {
        $this->response->shouldReceive('getStatusCode')->andReturn(200, 404);
        $this->guzzle->shouldReceive('get')->with("lists/list-1")->andReturn($this->response);
        $this->guzzle->shouldReceive('get')->with("lists/list-1/members/b642b4217b34b1e8d3bd915fc65c4452")->andReturn($this->response);
        $this->assertEquals('not found', $this->mc->checkStatus('list-1', 'test@test.com'));
    }

    public function testCheckStatusSuccess()
    {
        $this->response->shouldReceive('getStatusCode')->andReturn(200, 200);
        $this->response->shouldReceive('getBody')->andReturn(Mockery::self());
        $result = new stdClass;
        $result->status = 'testing';
        $this->response->shouldReceive('getContents')->andReturn(json_encode($result));
        $this->guzzle->shouldReceive('get')->with("lists/list-1")->andReturn($this->response);
        $this->guzzle->shouldReceive('get')->with("lists/list-1/members/b642b4217b34b1e8d3bd915fc65c4452")->andReturn($this->response);
        $this->assertEquals('testing', $this->mc->checkStatus('list-1', 'test@test.com'));
    }

    public function testCheckSubscribed()
    {
        $this->response->shouldReceive('getStatusCode')->andReturn(200, 200);
        $this->response->shouldReceive('getBody')->andReturn(Mockery::self());
        $result = new stdClass;
        $result->status = 'subscribed';
        $this->response->shouldReceive('getContents')->andReturn(json_encode($result));
        $this->guzzle->shouldReceive('get')->with("lists/list-1")->andReturn($this->response);
        $this->guzzle->shouldReceive('get')->with("lists/list-1/members/b642b4217b34b1e8d3bd915fc65c4452")->andReturn($this->response);
        $this->assertTrue($this->mc->check('list-1', 'test@test.com'));
    }

    public function testCheckNotSubscribed()
    {
        $this->response->shouldReceive('getStatusCode')->andReturn(200, 200);
        $this->response->shouldReceive('getBody')->andReturn(Mockery::self());
        $result = new stdClass;
        $result->status = 'unsubscribed';
        $this->response->shouldReceive('getContents')->andReturn(json_encode($result));
        $this->guzzle->shouldReceive('get')->with("lists/list-1")->andReturn($this->response);
        $this->guzzle->shouldReceive('get')->with("lists/list-1/members/b642b4217b34b1e8d3bd915fc65c4452")->andReturn($this->response);
        $this->assertFalse($this->mc->check('list-1', 'test@test.com'));
    }
}
