<?php declare(strict_types = 1);

namespace Neatous\SmsManager;

use Neatous\SmsManager\Acceptance\MessageAcceptance;
use Neatous\SmsManager\Message\Message;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ApiClient implements MessageSender
{

    private const string MESSAGE_PATH = '/message';

    private const string PRIORITY_MESSAGE_PATH = '/message/priority';

    private const int SUCCESS_STATUS_CODE = 200;

    private ApiKey $apiKey;

    private ClientInterface $httpClient;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    private BaseUri $baseUri;

    public function __construct(
        ApiKey $apiKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?BaseUri $baseUri = null,
    )
    {
        $this->apiKey = $apiKey;
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUri = $baseUri ?? BaseUri::default();
    }

    public function send(Message $message): MessageAcceptance
    {
        return $this->dispatch($message, self::MESSAGE_PATH);
    }

    public function sendPriority(Message $message): MessageAcceptance
    {
        return $this->dispatch($message, self::PRIORITY_MESSAGE_PATH);
    }

    private function dispatch(Message $message, string $path): MessageAcceptance
    {
        $body = json_encode($message->toRequestData(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $request = $this->requestFactory
            ->createRequest('POST', $this->baseUri->getValue() . $path)
            ->withHeader('x-api-key', $this->apiKey->getValue())
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw \Neatous\SmsManager\Exception\ApiRequestFailedException::forTransportFailure($exception);
        }

        $responseBody = (string) $response->getBody();

        if ($response->getStatusCode() !== self::SUCCESS_STATUS_CODE) {
            throw \Neatous\SmsManager\Exception\ApiRequestFailedException::forResponse($response->getStatusCode(), $responseBody);
        }

        return MessageAcceptance::fromJson($responseBody);
    }
}
