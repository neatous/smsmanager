<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Bridges\Tracy;

use Neatous\SmsManager\Fake\JournalEntry;
use Neatous\SmsManager\Fake\MessageJournal;
use Tracy\IBarPanel;

final class MessagePanel implements IBarPanel
{

    private const int ENTRY_LIMIT = 20;

    private const string ICON = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M2.5 2.5h11v8h-6l-3.5 3v-3h-1.5z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>';

    private MessageJournal $journal;

    public function __construct(MessageJournal $journal)
    {
        $this->journal = $journal;
    }

    public function getTab(): string
    {
        return '<span title="SmsManager journal">' . self::ICON . '&nbsp;' . $this->journal->countEntries() . '</span>';
    }

    public function getPanel(): string
    {
        $entries = $this->journal->getLatestEntries(self::ENTRY_LIMIT);
        $panel = '<h1>SmsManager journal</h1><div class="tracy-inner"><div class="tracy-inner-container">';

        if ($entries->isEmpty()) {
            return $panel . '<p>No messages have been written into the journal.</p></div></div>';
        }

        $rows = '';

        foreach ($entries as $entry) {
            $rows .= self::renderRow($entry);
        }

        return $panel
            . '<table><thead><tr><th>Time</th><th>Phone number</th><th>Body</th><th>Sender</th><th>Tags</th><th>Priority</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table></div></div>';
    }

    private static function renderRow(JournalEntry $entry): string
    {
        return '<tr>'
            . '<td>' . self::escape($entry->getSentAt()->format('H:i:s')) . '</td>'
            . '<td>' . self::escape($entry->getPhoneNumber()->getValue()) . '</td>'
            . '<td style="white-space:pre-wrap;max-width:30em">' . self::escape($entry->getBody()->getValue()) . '</td>'
            . '<td>' . self::escape($entry->getSender()?->getValue() ?? '') . '</td>'
            . '<td>' . self::escape($entry->getTags()?->toRequestValue() ?? '') . '</td>'
            . '<td>' . ($entry->isPriority() ? 'yes' : '') . '</td>'
            . '</tr>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
