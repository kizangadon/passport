<?php

namespace Laravel\Passport\Tests;

use Mockery as m;
use Laravel\Passport\Token;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use PHPUnit\Framework\TestCase;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;

class CheckClientCredentialsTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function test_request_is_passed_along_if_token_is_valid()
    {
        $resourceServer = m::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr = m::mock());
        $psr->shouldReceive('getAttribute')->with('oauth_user_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_access_token_id')->andReturn('token');
        $psr->shouldReceive('getAttribute')->with('oauth_scopes')->andReturn(['*']);

        $client = m::mock(Client::class);
        $client->shouldReceive('firstParty')->andReturnFalse();

        $token = m::mock(Token::class);
        $token->shouldReceive('getAttribute')->with('client')->andReturn($client);
        $token->shouldReceive('getAttribute')->with('scopes')->andReturn(['*']);

        $tokenRepository = m::mock(TokenRepository::class);
        $tokenRepository->shouldReceive('find')->with('token')->andReturn($token);

        $middleware = new CheckClientCredentials($resourceServer, $tokenRepository);

        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer token');

        $response = $middleware->handle($request, function () {
            return 'response';
        });

        $this->assertEquals('response', $response);
    }

    public function test_request_is_passed_along_if_token_and_scope_are_valid()
    {
        $resourceServer = m::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr = m::mock());
        $psr->shouldReceive('getAttribute')->with('oauth_user_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_access_token_id')->andReturn('token');
        $psr->shouldReceive('getAttribute')->with('oauth_scopes')->andReturn(['see-profile']);

        $client = m::mock(Client::class);
        $client->shouldReceive('firstParty')->andReturnFalse();

        $token = m::mock(Token::class);
        $token->shouldReceive('getAttribute')->with('client')->andReturn($client);
        $token->shouldReceive('getAttribute')->with('scopes')->andReturn(['see-profile']);
        $token->shouldReceive('cant')->with('see-profile')->andReturnFalse();

        $tokenRepository = m::mock(TokenRepository::class);
        $tokenRepository->shouldReceive('find')->with('token')->andReturn($token);

        $middleware = new CheckClientCredentials($resourceServer, $tokenRepository);

        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer token');

        $response = $middleware->handle($request, function () {
            return 'response';
        }, 'see-profile');

        $this->assertEquals('response', $response);
    }

    /**
     * @expectedException \Illuminate\Auth\AuthenticationException
     */
    public function test_exception_is_thrown_when_oauth_throws_exception()
    {
        $tokenRepository = m::mock(TokenRepository::class);
        $resourceServer = m::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andThrow(
            new OAuthServerException('message', 500, 'error type')
        );

        $middleware = new CheckClientCredentials($resourceServer, $tokenRepository);

        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer token');

        $middleware->handle($request, function () {
            return 'response';
        });
    }

    /**
     * @expectedException \Laravel\Passport\Exceptions\MissingScopeException
     */
    public function test_exception_is_thrown_if_token_does_not_have_required_scopes()
    {
        $resourceServer = m::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr = m::mock());
        $psr->shouldReceive('getAttribute')->with('oauth_user_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_access_token_id')->andReturn('token');
        $psr->shouldReceive('getAttribute')->with('oauth_scopes')->andReturn(['foo', 'notbar']);

        $client = m::mock(Client::class);
        $client->shouldReceive('firstParty')->andReturnFalse();

        $token = m::mock(Token::class);
        $token->shouldReceive('getAttribute')->with('client')->andReturn($client);
        $token->shouldReceive('getAttribute')->with('scopes')->andReturn(['foo', 'notbar']);
        $token->shouldReceive('cant')->with('foo')->andReturnFalse();
        $token->shouldReceive('cant')->with('bar')->andReturnTrue();

        $tokenRepository = m::mock(TokenRepository::class);
        $tokenRepository->shouldReceive('find')->with('token')->andReturn($token);

        $middleware = new CheckClientCredentials($resourceServer, $tokenRepository);

        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer token');

        $response = $middleware->handle($request, function () {
            return 'response';
        }, 'foo', 'bar');
    }

    /**
     * @expectedException \Illuminate\Auth\AuthenticationException
     */
    public function test_exception_is_thrown_if_token_belongs_to_first_party_client()
    {
        $resourceServer = m::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr = m::mock());
        $psr->shouldReceive('getAttribute')->with('oauth_user_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_access_token_id')->andReturn('token');
        $psr->shouldReceive('getAttribute')->with('oauth_scopes')->andReturn(['*']);

        $client = m::mock(Client::class);
        $client->shouldReceive('firstParty')->andReturnTrue();

        $token = m::mock(Token::class);
        $token->shouldReceive('getAttribute')->with('client')->andReturn($client);

        $tokenRepository = m::mock(TokenRepository::class);
        $tokenRepository->shouldReceive('find')->with('token')->andReturn($token);

        $middleware = new CheckClientCredentials($resourceServer, $tokenRepository);

        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer token');

        $response = $middleware->handle($request, function () {
            return 'response';
        });
    }
}
