<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Fake;

final readonly class MessageJournal
{

    private string $journalDirectory;

    public function __construct(string $journalDirectory)
    {
        $this->journalDirectory = $journalDirectory;
    }

    public function getLatestEntries(int $limit): JournalEntryList
    {
        $entryLimit = max($limit, 0);
        $entries = [];

        foreach (array_slice(array_reverse($this->listFiles()), 0, $entryLimit) as $file) {
            $entries[] = self::readEntry($file);
        }

        return JournalEntryList::fromEntries(...$entries);
    }

    public function countEntries(): int
    {
        return count($this->listFiles());
    }

    public function clear(): void
    {
        foreach ($this->listFiles() as $file) {
            if (!@unlink($file)) {
                throw new \Neatous\SmsManager\Exception\JournalException(sprintf('Journal entry "%s" cannot be deleted.', $file));
            }
        }
    }

    /** @return list<string> */
    private function listFiles(): array
    {
        if (!is_dir($this->journalDirectory)) {
            return [];
        }

        $files = glob($this->journalDirectory . DIRECTORY_SEPARATOR . '*.json');

        if ($files === false) {
            throw new \Neatous\SmsManager\Exception\JournalException(sprintf('Journal directory "%s" cannot be listed.', $this->journalDirectory));
        }

        return $files;
    }

    private static function readEntry(string $file): JournalEntry
    {
        $contents = @file_get_contents($file);

        if ($contents === false) {
            throw new \Neatous\SmsManager\Exception\JournalException(sprintf('Journal entry "%s" cannot be read.', $file));
        }

        return JournalEntry::fromJson($contents);
    }
}
