<?php

namespace Krdinesh\OAuth2\Client\Test\Provider;

use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Mockery as m;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of SmartRecruitersProviderTest
 *
 * @author krdinesh
 */
class SmartRecruitersProviderTest extends \PHPUnit_Framework_TestCase {

    use QueryBuilderTrait;

    protected $provider;

    //put your code here

    protected function setUp() {
        $this->provider = new \Krdinesh\OAuth2\Client\Provider\SmartRecruitersProvider([
            "clientId"     => "mockery",
            "clientSecret" => "mockery",
            "redirectUri"  => "mockery"
        ]);
    }

    public function testGetBaseAccessTokenUrl() {
        $params = [];
        $url    = $this->provider->getBaseAccessTokenUrl($params);
        $uri    = parse_url($url);
        $this->assertEquals('/identity/oauth/token', $uri['path']);
    }

    public function testGetAuthorizationUrl() {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        $this->assertEquals('/identity/oauth/allow', $uri['path']);
    }

    public function testScopes() {
        $scopeSeparator = ' ';
        $options        = ['scope' => [uniqid(), uniqid()]];
        $query          = ['scope' => implode($scopeSeparator, $options['scope'])];
        $url            = $this->provider->getAuthorizationUrl($options);
        $encodedScope   = $this->buildQueryString($query);
        $this->assertContains(urldecode($encodedScope), $url);
    }

    public function testGetBaseAuthorizationUrl() {
        // Acting
        $scopeSeparater = $this->provider->getAuthorizationUrl();
        $uri            = parse_url($scopeSeparater, PHP_URL_QUERY);
        parse_str($uri, $query);

        // Asserting  Following
        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testGetAccessToken() {
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token", "scope":"repo gist", "token_type":"bearer"}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $client   = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);
        $token    = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals('mock_access_token', $token->getToken());
    }

    public function testUserData() {
        $email        = \uniqid();
        $firstName    = \uniqid();
        $lastName     = \uniqid();
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"access_token": "mock_access_token", "expires_in": 3600}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn('{"email":"' . $email . '","firstName":"' . $firstName . '","lastName":"' . $lastName . '"}');
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $client       = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
                ->times(2)
                ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);
        $token        = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user         = $this->provider->getResourceOwner($token);
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals($email, $user->toArray()['email']);
        $this->assertEquals($firstName, $user->getFirstName());
        $this->assertEquals($firstName, $user->toArray()['firstName']);
        $this->assertEquals($lastName, $user->getLastName());
        $this->assertEquals($lastName, $user->toArray()['lastName']);
    }

    public function testMissingUserData() {
        $email        = \uniqid();
        $firstName    = \rand(1000, 9999);
        $lastName     = \uniqid();
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"access_token": "mock_access_token", "expires_in": 3600}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn('{"email": "' . $email . '", "firstName": "' . $firstName . '", "lastName": "' . $lastName . '"}');
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $client       = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
                ->times(2)
                ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);
        $token        = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user         = $this->provider->getResourceOwner($token);
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals($email, $user->toArray()['email']);
        $this->assertEquals($firstName, $user->getFirstName());
        $this->assertEquals($firstName, $user->toArray()['firstName']);
        $this->assertEquals($lastName, $user->getLastName()); // https://github.com/thephpleague/oauth2-linkedin/issues/4
        $this->assertEquals($lastName, $user->toArray()['lastName']);
    }

    public function testExceptionThrownWhenErrorObjectReceived() {
        $message      = uniqid();
        $status       = rand(400, 600);
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"error_description": "' . $message . '","error": "invalid_request"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client       = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
                ->times(1)
                ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $token        = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    protected function tearDown() {
        m::close();
        parent::tearDown();
    }

}
