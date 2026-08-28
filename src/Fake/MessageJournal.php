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
        $entries = $this->readEntries();

        usort($entries, static function (JournalEntry $left, JournalEntry $right): int {
            return $right->getSentAt() <=> $left->getSentAt();
        });

        return JournalEntryList::fromEntries(...array_slice($entries, 0, max($limit, 0)));
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

    /** @return list<JournalEntry> */
    private function readEntries(): array
    {
        $entries = [];

        foreach ($this->listFiles() as $file) {
            $contents = @file_get_contents($file);

            if ($contents === false) {
                throw new \Neatous\SmsManager\Exception\JournalException(sprintf('Journal entry "%s" cannot be read.', $file));
            }

            $entries[] = JournalEntry::fromJson($contents);
        }

        return $entries;
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
}
