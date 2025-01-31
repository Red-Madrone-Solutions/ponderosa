<?php

namespace Rms\Ponderosa;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Rms\Ponderosa\Data\ResponseData;

class Client
{

    protected GuzzleHttpClient $guzzle;

    public function __construct(
        protected string $api_key,
        protected string $index,
        protected ?string $index_host = null,
    )
    {
        if ( empty($this->index_host) ) {
            $this->index_host = $this->getIndexHost();
        }

        // Try to get the Guzzle client from the container
        if (function_exists('app')) {
            $this->guzzle = app(GuzzleHttpClient::class);
        }

        // If the Guzzle client is not set, create a new one
        if (!isset($this->guzzle)) {
            $this->guzzle = new GuzzleHttpClient([
                'base_uri' => $this->index_host,
                'headers'  => [
                    'Api-Key'      => $this->api_key,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
        }
    }

    protected function getIndexHost() : string
    {
        $client = new GuzzleHttpClient();
        $response = $client->request(
            'GET',
            'https://api.pinecone.io/indexes/' . $this->index,
            [
                'headers' => [
                    'Api-Key' => $this->api_key,
                    'Accept'  => 'application/json',
                ],
            ]
        );
        $data = json_decode($response->getBody(), true);
        return 'https://' . $data['host'] . '/';
    }

    public function describeIndexStats()
    {
        $request = $this->buildRequest(
            method: 'POST',
            uri: 'describe_index_stats',
        );
        $response = $this->guzzle->send($request);
        return json_decode($response->getBody(), true);
    }

    public function query(
        array $vector,
        string $namespace = '',
        int $count = 10,
        bool $include_values = false,
        bool $include_metadata = false,
        array $filter = [],
    ) : ResponseData
    {
        $body = [
            'vector' => $vector,
            'topK' => $count,
            'includeValues' => $include_values,
            'includeMetadata' => $include_metadata,
        ];
        if ( !empty($namespace) ) {
            $body['namespace'] = $namespace;
        }

        if ( !empty($filter) ) {
            $body['filter'] = $filter;
        }

        $request = $this->buildRequest(
            method: 'POST',
            uri: '/query',
            body: $body,
        );

        $response = $this->guzzle->send($request);
        return new ResponseData($response);
    }


    protected function buildRequest(
        string $method,
        string $uri,
        array $body = [],
    ) : Request
    {
        $request = new Request(
            method: $method,
            uri: $uri,
            body: json_encode($body),
        );
        if ($this->api_key) {
            $request = $request->withHeader('Api-Key', $this->api_key);
        }
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withHeader('Accept', 'application/json');
        $request = $request->withUri(new Uri($this->index_host . $uri));
        return $request;
    }

    public function upsert(
        array $vectors,
        string $namespace = '',
    ) : ResponseData
    {
        $body = [
            'vectors' => $vectors,
        ];
        if ( !empty($namespace) ) {
            $body['namespace'] = $namespace;
        }

        $request = $this->buildRequest(
            method: 'POST',
            uri: 'vectors/upsert',
            body: $body,
        );
        $response = $this->guzzle->send($request);
        return new ResponseData($response);
    }

    public function delete(
        string $vector_id,
        string $namespace = '',
    ) : ResponseData
    {
        return $this->deleteBulk( [ $vector_id ], $namespace );
    }

    public function deleteBulk(
        array $vector_ids,
        string $namespace = '',
    ) : ResponseData
    {
        $body = [
            'deleteAll' => false,
            'ids' => $vector_ids,
        ];

        if ( !empty($namespace) ) {
            $body['namespace'] = $namespace;
        }

        $request = $this->buildRequest(
            method: 'POST',
            uri: 'vectors/delete',
            body: $body,
        );

        return new ResponseData( $this->guzzle->send($request) );
    }

    public function deleteAll(
        string $namespace
    ) : ResponseData
    {
        $body = [
            'deleteAll' => true,
            'namespace' => $namespace,
        ];

        $request = $this->buildRequest(
            method: 'POST',
            uri: 'vectors/delete',
            body: $body,
        );

        return new ResponseData( $this->guzzle->send($request) );
    }
}


