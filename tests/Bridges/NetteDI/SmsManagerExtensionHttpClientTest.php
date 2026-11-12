<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Bridges\NetteDI;

use GuzzleHttp\Client as GuzzleHttpClient;
use Neatous\SmsManager\ApiClient;
use Neatous\SmsManager\Client;
use Neatous\SmsManager\Fake\FakeClient;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmsManagerExtensionHttpClientTest extends TestCase
{

    private const string EXTENSION_SECTION = "extensions:\n\tsmsManager: Neatous\\SmsManager\\Bridges\\NetteDI\\SmsManagerExtension\n";

    private string $temporaryDirectory;

    #[DataProvider('invalidHttpClientProvider')]
    public function testInvalidHttpClientIsRefusedInFakeMode(string $value, string $message): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidExtensionConfigurationException::class);
        $this->expectExceptionMessage($message);
        $this->createContainer(
            self::EXTENSION_SECTION . "\nservices:\n\tother: stdClass\n"
            . "\nsmsManager:\n\tfake: true\n\tjournalDir: " . $this->journalDirectory() . "\n\thttpClient: " . $value . "\n"
        );
    }

    public function testHttpClientReferenceThroughAliasIsAccepted(): void
    {
        $container = $this->createContainer(
            self::EXTENSION_SECTION . "\nservices:\n\tdedicatedClient:\n\t\tfactory: GuzzleHttp\\Client\n\t\tautowired: no\n\taliasClient: @dedicatedClient\n"
            . "\nsmsManager:\n\tfake: true\n\tjournalDir: " . $this->journalDirectory() . "\n\thttpClient: @aliasClient\n"
        );

        self::assertInstanceOf(FakeClient::class, $container->getByType(Client::class));
    }

    public function testHttpClientReferenceByTypeIsAccepted(): void
    {
        $container = $this->createContainer(
            self::EXTENSION_SECTION . "\nservices:\n\tdedicatedClient:\n\t\tfactory: GuzzleHttp\\Client\n"
            . "\nsmsManager:\n\tfake: true\n\tjournalDir: " . $this->journalDirectory() . "\n\thttpClient: @GuzzleHttp\\Client\n"
        );

        self::assertInstanceOf(FakeClient::class, $container->getByType(Client::class));
    }

    public function testApiModeAcceptsInlineGuzzleClientDefinition(): void
    {
        $container = $this->createContainer(
            self::EXTENSION_SECTION . "\nservices:\n\thttpFactory: GuzzleHttp\\Psr7\\HttpFactory\n"
            . "\nsmsManager:\n\tapiKey: test-api-key\n\thttpClient: GuzzleHttp\\Client([timeout: 15])\n"
        );

        self::assertInstanceOf(ApiClient::class, $container->getByType(Client::class));
        self::assertNull($container->getByType(GuzzleHttpClient::class, false));
    }

    public function testHttpClientReferenceByInterfaceOfResolvedServiceIsAccepted(): void
    {
        $container = $this->createContainer(
            self::EXTENSION_SECTION . "\nservices:\n\tdedicatedClient: GuzzleHttp\\Client\n"
            . "\nsmsManager:\n\tfake: true\n\tjournalDir: " . $this->journalDirectory() . "\n\thttpClient: @GuzzleHttp\\ClientInterface\n"
        );

        self::assertInstanceOf(FakeClient::class, $container->getByType(Client::class));
    }

    #[DataProvider('acceptedHttpClientFactoryProvider')]
    public function testHttpClientFactoryWithExistingOriginIsAcceptedInFakeMode(string $value): void
    {
        $container = $this->createContainer(
            self::EXTENSION_SECTION . "\nservices:\n\texisting: stdClass\n"
            . "\nsmsManager:\n\tfake: true\n\tjournalDir: " . $this->journalDirectory() . "\n\thttpClient: " . $value . "\n"
        );

        self::assertInstanceOf(FakeClient::class, $container->getByType(Client::class));
    }

    /** @return iterable<string, array{string}> */
    public static function acceptedHttpClientFactoryProvider(): iterable
    {
        yield 'method of an existing service' => ['@existing::method()'];
        yield 'chained method of an existing class' => ['stdClass()::method()'];
        yield 'chained method of a chained method of an existing service' => ['@existing::create()::build()'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidHttpClientProvider(): iterable
    {
        yield 'static factory of a missing class' => ['Missing\\Factory::create()', 'the class "Missing\\Factory", which does not exist'];
        yield 'factory of a missing service' => ['@typo::create()', 'refers to the service "typo", which is not defined'];
        yield 'chained factory of a missing class' => ['Missing\\Builder()::build()', 'the class "Missing\\Builder", which does not exist'];
        yield 'called reference to a missing service' => ['@typo()', 'must not call the service "typo"; use "@typo"'];
        yield 'called reference to a service of another type' => ['@other()', 'must not call the service "other"; use "@other"'];
        yield 'chained factory of a called reference' => ['@other()::build()', 'must not call the service "other"; use "@other"'];
        yield 'chained factory of a called missing service' => ['@typo()::build()', 'must not call the service "typo"; use "@typo"'];
        yield 'called reference with arguments' => ['@other([timeout: 15])', 'must not call the service "other"; use "@other"'];
        yield 'class that is not a PSR-18 client' => ['stdClass()', 'the class "stdClass", which is not a PSR-18 client'];
        yield 'plain function' => ['strlen()', 'the class "strlen", which does not exist'];
        yield 'reference to a service of another type' => ['@other', 'of the type "stdClass", which is not a PSR-18 client'];
        yield 'empty value' => ["''", 'must be a service reference'];
    }

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'smsmanager-di-' . uniqid();

        if (!@mkdir($this->temporaryDirectory, 0777, true) && !is_dir($this->temporaryDirectory)) {
            self::fail(sprintf('The temporary directory "%s" cannot be created.', $this->temporaryDirectory));
        }
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->temporaryDirectory);
    }

    private function createContainer(string $neon): Container
    {
        $configFile = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'config.neon';
        file_put_contents($configFile, $neon);

        $loader = new ContainerLoader($this->temporaryDirectory, true);
        $containerClass = $loader->load(static function (Compiler $compiler) use ($configFile): ?string {
            $compiler->addExtension('extensions', new ExtensionsExtension());
            $compiler->loadConfig($configFile);

            return null;
        }, $neon);

        return new $containerClass();
    }

    private function journalDirectory(): string
    {
        return $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'journal';
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*');

        foreach ($files === false ? [] : $files as $file) {
            if (is_dir($file)) {
                self::removeDirectory($file);

                continue;
            }

            unlink($file);
        }

        rmdir($directory);
    }
}
