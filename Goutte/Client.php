<?php

/*
 * This file is part of the Goutte package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Goutte;

use Symfony\Component\BrowserKit\Client as BaseClient;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

use Guzzle\Http\Exception\CurlException;
use Guzzle\Http\Message\RequestInterface as GuzzleRequestInterface;
use Guzzle\Http\Message\Response as GuzzleResponse;
use Guzzle\Http\ClientInterface as GuzzleClientInterface;
use Guzzle\Http\Client as GuzzleClient;

/**
 * Client.
 *
 * @package Goutte
 * @author  Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author  Michael Dowling <michael@guzzlephp.org>
 */
class Client extends BaseClient
{
    const VERSION = '0.2';

    protected $headers = array();
    protected $auth = null;
    protected $client;

    public function setClient(GuzzleClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    public function getClient()
    {
        if (!$this->client) {
            $this->client = new GuzzleClient();
        }

        return $this->client;
    }

    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function setAuth($user, $password = '', $type = GuzzleRequestInterface::AUTH_BASIC)
    {
        $this->auth = array(
            'user' => $user,
            'password' => $password,
            'type'     => $type
        );

        return $this;
    }

    protected function doRequest($request)
    {
        $guzzleRequest = $this->getClient()->createRequest(
            strtoupper($request->getMethod()),
            $request->getUri(),
            $this->headers,
            $request->getParameters()
        );

        if ($this->auth !== null) {
            $guzzleRequest->setAuth(
                $this->auth['user'],
                $this->auth['password'],
                $this->auth['type']
            );
        }

        foreach ($this->getCookieJar()->allValues($request->getUri()) as $name => $value) {
            $guzzleRequest->addCookie($name, $value);
        }

        if ('POST' == $request->getMethod()) {
            $postFiles = array();
            foreach ($request->getFiles() as $name => $info) {
                if (isset($info['tmp_name']) && '' !== $info['tmp_name']) {
                    $postFiles[$name] = $info['tmp_name'];
                }
            }
            if (!empty($postFiles)) {
                $guzzleRequest->addPostFiles($postFiles);
            }
        }

        $guzzleRequest->setHeader('User-Agent', $this->server['HTTP_USER_AGENT']);

        $guzzleRequest->getCurlOptions()
            ->set(CURLOPT_FOLLOWLOCATION, false)
            ->set(CURLOPT_MAXREDIRS, 0)
            ->set(CURLOPT_TIMEOUT, 30);

        // Let BrowserKit handle redirects
        try {
            $response = $guzzleRequest->send();
        } catch (CurlException $e) {
            if (!strpos($e->getMessage(), 'redirects')) {
                throw $e;
            }

            $response = $e->getResponse();
        }

        return $this->createResponse($response);
    }

    protected function createResponse(GuzzleResponse $response)
    {
        return new Response($response->getBody(true), $response->getStatusCode(), $response->getHeaders()->getAll());
    }
}