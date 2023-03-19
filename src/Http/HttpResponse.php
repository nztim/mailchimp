<?php

namespace NZTim\Mailchimp\Http;

use Psr\Http\Message\ResponseInterface;

class HttpResponse
{
    private ResponseInterface $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function body(): string
    {
        return (string) $this->response->getBody();
    }

    public function json(): array
    {
        $result = json_decode($this->response->getBody(), true);
        return is_null($result) ? [] : $result;
    }

    public function header($header): string
    {
        return $this->response->getHeaderLine($header);
    }

    public function headers()
    {
        $headers = [];
        foreach ($this->response->getHeaders() as $key => $value) {
            $headers[$key] = $value[0];
        }
        return $headers;
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function isSuccess(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function isOk(): bool
    {
        return $this->isSuccess();
    }

    public function isRedirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }

    public function isClientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    public function isServerError(): bool
    {
        return $this->status() >= 500;
    }

    public function __toString(): string
    {
        return $this->body();
    }
}
