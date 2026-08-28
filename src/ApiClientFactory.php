<?php declare(strict_types = 1);

namespace Neatous\SmsManager;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ApiClientFactory implements MessageSenderFactory
{

    private ClientInterface $httpClient;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    private ?BaseUri $baseUri;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?BaseUri $baseUri = null,
    )
    {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUri = $baseUri;
    }

    public function create(ApiKey $apiKey): MessageSender
    {
        return new ApiClient($apiKey, $this->httpClient, $this->requestFactory, $this->streamFactory, $this->baseUri);
    }
}
