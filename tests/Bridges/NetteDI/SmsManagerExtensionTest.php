<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Bridges\NetteDI;

use Neatous\SmsManager\ApiClient;
use Neatous\SmsManager\ApiClientFactory;
use Neatous\SmsManager\Fake\FakeMessageSender;
use Neatous\SmsManager\Fake\FakeMessageSenderFactory;
use Neatous\SmsManager\Fake\MessageJournal;
use Neatous\SmsManager\MessageSender;
use Neatous\SmsManager\MessageSenderFactory;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use PHPUnit\Framework\TestCase;

final class SmsManagerExtensionTest extends TestCase
{

    private const string EXTENSION_SECTION = "extensions:\n\tsmsManager: Neatous\\SmsManager\\Bridges\\NetteDI\\SmsManagerExtension\n";

    private const string PSR_SERVICES_SECTION = "services:\n\thttpClient: GuzzleHttp\\Client\n\thttpFactory: GuzzleHttp\\Psr7\\HttpFactory\n";

    private string $temporaryDirectory;

    public function testFakeModeRegistersJournalledServices(): void
    {
        $container = $this->createContainer(
            self::EXTENSION_SECTION . "\nsmsManager:\n\tfake: true\n\tjournalDir: " . $this->journalDirectory() . "\n"
        );

        self::assertInstanceOf(FakeMessageSender::class, $container->getByType(MessageSender::class));
        self::assertInstanceOf(FakeMessageSenderFactory::class, $container->getByType(MessageSenderFactory::class));
        self::assertNotNull($container->getByType(MessageJournal::class, false));
    }

    public function testFakeModeWithoutJournalDirectoryIsRefused(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidExtensionConfigurationException::class);
        $this->createContainer(self::EXTENSION_SECTION . "\nsmsManager:\n\tfake: true\n");
    }

    public function testApiModeWithApiKeyRegistersApiClient(): void
    {
        $container = $this->createContainer(
            self::EXTENSION_SECTION . "\n" . self::PSR_SERVICES_SECTION . "\nsmsManager:\n\tapiKey: test-api-key\n\tbaseUri: https://api-mock.smsmngr.com/v2\n"
        );

        self::assertInstanceOf(ApiClient::class, $container->getByType(MessageSender::class));
        self::assertInstanceOf(ApiClientFactory::class, $container->getByType(MessageSenderFactory::class));
    }

    public function testApiModeWithoutApiKeyRegistersFactoryOnly(): void
    {
        $container = $this->createContainer(self::EXTENSION_SECTION . "\n" . self::PSR_SERVICES_SECTION);

        self::assertInstanceOf(ApiClientFactory::class, $container->getByType(MessageSenderFactory::class));
        self::assertNull($container->getByType(MessageSender::class, false));
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
