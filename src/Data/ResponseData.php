<?php

namespace Rms\Ponderosa\Data;

use GuzzleHttp\Psr7\Response;

class ResponseData
{
    public function __construct(
        protected Response $response,
    ) { }

    public function body() : array {
        return $this->json();
    }

    public function json() : array
    {
        return json_decode($this->response->getBody());
    }

    public function isSuccessful() : bool
    {
        $status_code = (int) $this->response->getStatusCode();
        return $status_code >= 200 && $status_code < 300;
    }

}
