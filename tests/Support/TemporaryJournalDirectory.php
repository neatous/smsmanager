<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Support;

final class TemporaryJournalDirectory
{

    public static function create(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'smsmanager-tests-' . uniqid();
    }

    public static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*');

        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
}
