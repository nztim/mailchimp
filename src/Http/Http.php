<?php

namespace NZTim\Mailchimp\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;

class Http
{
    /** @var string */
    private $bodyFormat;
    /** @var array */
    private $options;

    public function __construct()
    {
        $this->bodyFormat = 'json';
        $this->options = [
            'http_errors' => false,
            'headers'     => [],
        ];
    }

    public function withoutRedirecting(): Http
    {
        $this->options['allow_redirects'] = false;
        return $this;
    }

    public function asJson(): Http
    {
        return $this->bodyFormat('json')->contentType('application/json');
    }

    public function asFormParams(): Http
    {
        return $this->bodyFormat('form_params')->contentType('application/x-www-form-urlencoded');
    }

    public function asMultipart(): Http
    {
        return $this->bodyFormat('multipart');
    }

    private function bodyFormat(string $format): Http
    {
        $this->bodyFormat = $format;
        return $this;
    }

    public function contentType(string $contentType): Http
    {
        return $this->withHeaders(['Content-Type' => $contentType]);
    }

    public function accept(string $header): Http
    {
        return $this->withHeaders(['Accept' => $header]);
    }

    public function withHeaders(array $headers): Http
    {
        $this->options['headers'] = array_merge($this->options['headers'], $headers);
        return $this;
    }

    public function withBasicAuth(string $username, string $password)
    {
        $this->options['auth'] = [$username, $password];
        return $this;
    }

    public function withDigestAuth(string $username, string $password)
    {
        $this->options['auth'] = [$username, $password, 'digest'];
        return $this;
    }

    public function timeout(int $seconds)
    {
        $this->options['timeout'] = $seconds;
        return $this;
    }

    public function get($url, $params = [])
    {
        return $this->send('GET', $url, [], $params); // Use query instead of body for GET
    }

    public function post($url, $params = [])
    {
        return $this->send('POST', $url, $params);
    }

    public function patch($url, $params = [])
    {
        return $this->send('PATCH', $url, $params);
    }

    public function put($url, $params = [])
    {
        return $this->send('PUT', $url, $params);
    }

    public function delete($url, $params = [])
    {
        return $this->send('DELETE', $url, $params);
    }

    public function send($method, $url, $params, $query = [])
    {
        $this->options['query'] = $query;
        if ($params) {
            $this->options[$this->bodyFormat] = $params;
        }
        $this->mergeUrlQuery($url);
        try {
            return new HttpResponse((new GuzzleClient())->request($method, $url, $this->options));
        } catch (ConnectException $e) {
            throw new ConnectionException($e->getMessage(), 0, $e);
        }
    }

    private function mergeUrlQuery(string $url)
    {
        // Parse URL query string and turn it into an array
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        if ($query) {
            $this->options['query'] = array_merge($this->options['query'], $query);
        }
    }
}
