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
            'journalDir' => Expect::string()->nullable(),
            'panel' => Expect::bool(true),
        ]);
    }

    public function loadConfiguration(): void
    {
        $defaultSender = $this->readDefaultSender();

        if ($this->readBool('fake', false)) {
            $this->registerFakeServices($defaultSender);

            return;
        }

        $this->registerApiServices($defaultSender);
    }

    public function beforeCompile(): void
    {
        if (!$this->readBool('fake', false) || !$this->readBool('panel', true)) {
            return;
        }

        $builder = $this->getContainerBuilder();
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
            throw new \Neatous\SmsManager\Exception\InvalidExtensionConfigurationException(
                sprintf('The option "%s" must be set when "%s" is enabled.', $this->prefix('journalDir'), $this->prefix('fake'))
            );
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

    private function registerApiServices(?Statement $defaultSender): void
    {
        $baseUri = $this->readString('baseUri');
        $arguments = ['defaultSender' => $defaultSender];

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
}
