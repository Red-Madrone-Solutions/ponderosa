<?php

namespace Rms\Ponderosa;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Psr7\Request;
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

        $this->guzzle = new GuzzleHttpClient([
            'base_uri' => $this->index_host,
            'headers'  => [
                'Api-Key'      => $this->api_key,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
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
        $request = new Request('POST', 'describe_index_stats');
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

        $request = new Request(
            method: 'POST',
            uri: '/query',
            body: json_encode($body),
        );

        $response = $this->guzzle->send($request);
        return new ResponseData($response);
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

        $request = new Request(
            method: 'POST',
            uri: 'vectors/upsert',
            body: json_encode($body),
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

        $request = new Request(
            method: 'POST',
            uri: 'vectors/delete',
            body: json_encode($body),
        );

        return new ResponseData( $this->guzzle->send($request) );
    }
}


