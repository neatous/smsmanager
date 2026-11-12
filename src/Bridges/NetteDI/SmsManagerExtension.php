<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Bridges\NetteDI;

use Neatous\SmsManager\ApiClientFactory;
use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\BaseUri;
use Neatous\SmsManager\Bridges\Tracy\MessagePanel;
use Neatous\SmsManager\Client;
use Neatous\SmsManager\Fake\FakeClient;
use Neatous\SmsManager\Fake\FakeClientFactory;
use Neatous\SmsManager\Fake\MessageJournal;
use Neatous\SmsManager\Message\Sender;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Psr\Http\Client\ClientInterface;
use Tracy\Bar;

final class SmsManagerExtension extends CompilerExtension
{

    private ?string $barServiceName = null;

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'apiKey' => Expect::string()->nullable(),
            'baseUri' => Expect::string()->nullable(),
            'defaultSender' => Expect::string()->nullable(),
            'fake' => Expect::bool(false),
            'httpClient' => Expect::anyOf(Expect::string(), Expect::type(Statement::class))->nullable(),
            'journalDir' => Expect::string()->nullable(),
            'panel' => Expect::bool(true),
        ]);
    }

    public function loadConfiguration(): void
    {
        $defaultSender = $this->readDefaultSender();
        $httpClient = $this->readHttpClient();

        if ($this->readBool('fake', false)) {
            $this->registerFakeServices($defaultSender);

            return;
        }

        $this->registerApiServices($defaultSender, $httpClient);
    }

    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();

        $this->assertHttpClientExists($this->readHttpClient());

        if (!$this->readBool('fake', false) || !$this->readBool('panel', true)) {
            return;
        }

        $barServiceName = $builder->getByType(Bar::class);

        if ($barServiceName === null) {
            return;
        }

        $this->barServiceName = $barServiceName;

        $panel = new ServiceDefinition();
        $panel->setFactory(MessagePanel::class, [new Reference($this->prefix('journal'))]);
        $panel->setAutowired(false);
        $builder->addDefinition($this->prefix('panel'), $panel);
    }

    public function afterCompile(ClassType $class): void
    {
        if ($this->barServiceName === null) {
            return;
        }

        $this->initialization->addBody(
            '$this->getService(?)->addPanel($this->getService(?));',
            [$this->barServiceName, $this->prefix('panel')]
        );
    }

    private function registerFakeServices(?Statement $defaultSender): void
    {
        $journalDirectory = $this->readString('journalDir');

        if ($journalDirectory === null) {
            throw $this->refuse('The option "%s" must be set when "%s" is enabled.', $this->prefix('journalDir'), $this->prefix('fake'));
        }

        $journal = new ServiceDefinition();
        $journal->setFactory(MessageJournal::class, [$journalDirectory]);

        $client = new ServiceDefinition();
        $client->setFactory(FakeClient::class, [$journalDirectory, $defaultSender]);

        $clientFactory = new ServiceDefinition();
        $clientFactory->setFactory(FakeClientFactory::class, [$journalDirectory, $defaultSender]);

        $builder = $this->getContainerBuilder();
        $builder->addDefinition($this->prefix('journal'), $journal);
        $builder->addDefinition($this->prefix('client'), $client);
        $builder->addDefinition($this->prefix('clientFactory'), $clientFactory);
    }

    private function registerApiServices(?Statement $defaultSender, Reference|Statement|null $httpClient): void
    {
        $baseUri = $this->readString('baseUri');
        $arguments = ['defaultSender' => $defaultSender];

        if ($httpClient !== null) {
            $arguments['httpClient'] = $httpClient;
        }

        if ($baseUri !== null) {
            $arguments['baseUri'] = new Statement([BaseUri::class, 'fromString'], [$baseUri]);
        }

        $clientFactory = new ServiceDefinition();
        $clientFactory->setFactory(ApiClientFactory::class, $arguments);

        $builder = $this->getContainerBuilder();
        $builder->addDefinition($this->prefix('clientFactory'), $clientFactory);

        $apiKey = $this->readString('apiKey');

        if ($apiKey === null) {
            return;
        }

        $client = new ServiceDefinition();
        $client->setType(Client::class);
        $client->setFactory(
            new Statement(
                [new Reference($this->prefix('clientFactory')), 'create'],
                [new Statement([ApiKey::class, 'fromString'], [$apiKey])]
            )
        );
        $builder->addDefinition($this->prefix('client'), $client);
    }

    private function readDefaultSender(): ?Statement
    {
        $sender = $this->readString('defaultSender');

        if ($sender === null) {
            return null;
        }

        try {
            Sender::fromString($sender);
        } catch (\Neatous\SmsManager\Exception\InvalidSenderException $exception) {
            throw new \Neatous\SmsManager\Exception\InvalidExtensionConfigurationException(
                sprintf('The option "%s" contains an invalid sender "%s".', $this->prefix('defaultSender'), $sender),
                0,
                $exception
            );
        }

        return new Statement([Sender::class, 'fromString'], [$sender]);
    }

    private function assertHttpClientExists(Reference|Statement|null $httpClient): void
    {
        if ($httpClient instanceof Reference) {
            $this->assertReferenceResolves($httpClient, true);

            return;
        }

        if ($httpClient === null) {
            return;
        }

        $entity = $httpClient->getEntity();

        if (is_string($entity)) {
            // A plain function is refused, because its return type cannot be verified from the configuration.
            if (!class_exists($entity)) {
                throw $this->refuse('The option "%s" defines the service of the class "%s", which does not exist.', $this->prefix('httpClient'), $entity);
            }

            if (!is_a($entity, ClientInterface::class, true)) {
                throw $this->refuse(
                    'The option "%s" defines the service of the class "%s", which is not a PSR-18 client.',
                    $this->prefix('httpClient'),
                    $entity
                );
            }

            return;
        }

        // "@name()", "Class::method()" and "@service::method()": the type of the created object is not known, so only the origin is checked.
        $this->assertOriginExists(is_array($entity) ? $entity[0] : $entity, !is_array($entity));
    }

    private function assertOriginExists(mixed $origin, bool $isCalled): void
    {
        if ($origin instanceof Reference) {
            // Nette rewrites "@name()" to a call of a creation method without resolving aliases, so it is refused.
            if ($isCalled) {
                throw $this->refuse(
                    'The option "%1$s" must not call the service "%2$s"; use "@%2$s" instead.',
                    $this->prefix('httpClient'),
                    $origin->getValue()
                );
            }

            $this->assertReferenceResolves($origin, false);

            return;
        }

        if ($origin instanceof Statement) {
            // A chained origin such as "Class()::method()": only the existence of its own origin is checked.
            $entity = $origin->getEntity();

            // "@name()::method()" is rewritten like "@name()", so the call is refused before the origin is checked.
            $this->assertOriginExists(is_array($entity) ? $entity[0] : $entity, !is_array($entity));

            return;
        }

        if (is_string($origin) && !class_exists($origin)) {
            throw $this->refuse('The option "%s" defines the service of the class "%s", which does not exist.', $this->prefix('httpClient'), $origin);
        }
    }

    private function assertReferenceResolves(Reference $reference, bool $isClientItself): void
    {
        $builder = $this->getContainerBuilder();
        /** @var class-string $value */
        $value = $reference->getValue();

        if ($reference->isName()) {
            $aliases = $builder->getAliases();
            $name = $aliases[$value] ?? $value;
            $name = $builder->hasDefinition($name) ? $name : null;
        } else {
            $name = $builder->getByType($value);
        }

        if ($name === null) {
            throw $this->refuse('The option "%s" refers to the service "%s", which is not defined.', $this->prefix('httpClient'), $value);
        }

        if (!$isClientItself) {
            return;
        }

        // The type is checked only when it is already known; a definition without a resolved type is accepted.
        $type = $builder->getDefinition($name)->getType();

        if ($type !== null && !is_a($type, ClientInterface::class, true)) {
            throw $this->refuse(
                'The option "%s" refers to the service "%s" of the type "%s", which is not a PSR-18 client.',
                $this->prefix('httpClient'),
                $value,
                $type
            );
        }
    }

    private function readHttpClient(): Reference|Statement|null
    {
        $value = ((array) $this->getConfig())['httpClient'] ?? null;

        if ($value instanceof Statement) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        if (str_starts_with($value, '@') && strlen($value) > 1) {
            return new Reference(substr($value, 1));
        }

        if (class_exists($value)) {
            return new Statement($value);
        }

        throw $this->refuse(
            'The option "%s" must be a service reference such as "@name", an existing class name or a service definition such as "Class()", "%s" given.',
            $this->prefix('httpClient'),
            $value
        );
    }

    private function readString(string $key): ?string
    {
        $value = ((array) $this->getConfig())[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function readBool(string $key, bool $default): bool
    {
        $value = ((array) $this->getConfig())[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    private function refuse(
        string $format,
        string ...$arguments,
    ): \Neatous\SmsManager\Exception\InvalidExtensionConfigurationException
    {
        return new \Neatous\SmsManager\Exception\InvalidExtensionConfigurationException(sprintf($format, ...$arguments));
    }
}
