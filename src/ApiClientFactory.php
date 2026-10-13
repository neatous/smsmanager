<?php declare(strict_types = 1);

namespace Neatous\SmsManager;

use Neatous\SmsManager\Message\Sender;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ApiClientFactory implements ClientFactory
{

    private ClientInterface $httpClient;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    private ?BaseUri $baseUri;

    private ?Sender $defaultSender;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?BaseUri $baseUri = null,
        ?Sender $defaultSender = null,
    )
    {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUri = $baseUri;
        $this->defaultSender = $defaultSender;
    }

    public function create(ApiKey $apiKey): Client
    {
        return new ApiClient($apiKey, $this->httpClient, $this->requestFactory, $this->streamFactory, $this->baseUri, $this->defaultSender);
    }
}
