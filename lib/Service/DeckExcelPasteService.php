<?php

declare(strict_types=1);

namespace OCA\Deck\Service;

use DateTimeImmutable;
use OC\Log;

class DeckExcelPasteService
{
    const OVERFLOW_KEY = 'overflow';
    const TITLE_FORMAT = '[%s]%s';

    public function __construct(
        private readonly Log $logger,
    ) {
    }


    private function getKeyValueList(): array
    {
        return [
            'number_url'                  => 'Nummer URL',
			'date_through' 				  => 'Datum doorgezet',
            'url'                         => 'URL',
            'warning'                     => 'Waarschuwing',
            'material'                    => 'Materiaal',
            'tasks'                       => 'Taken',
            'stream'                      => 'Stroming',
            'standardization'             => 'Standaardisering',
            'customization'               => 'Maatwerk',
            'type_material'               => 'Type materiaal',
            'exemptions'                  => 'Exempties',
            'grouping_person'             => 'Groepering/Persoon',
            'explanation'                 => 'Toelichting',
            'code'                        => 'Code',
            'hsp'                         => 'HSP',

            // Filled later by assessor
            'file_number'                 => 'Dossiernummer',
			'perci_number' 				  => 'Perci nummer',
            'online_offline'              => 'Online/Offline',
            'vb_sent_out'                 => 'VB verstuurd',
            'date'                        => 'Datum',
            'inaccessible_within_an_hour' => 'Ontoegankelijk binnen een uur',
            'blocked_deleted'             => 'Geblokkeerd/Verwijderd',
            'message_channel_profile'     => 'Bericht/Kanaal/Profiel',
            self::OVERFLOW_KEY            => 'Overige velden',
        ];
    }

    private function getKeyList(): array
    {
        return array_keys($this->getKeyValueList());
    }

    public function excelStringCheck(string $excelString): bool
    {
        return str_starts_with($excelString, '-x ');
    }

    /**
     * @throws \Exception
     */
    public function formatString(string $excelString): array
    {
        if (!$this->excelStringCheck($excelString) && preg_match('/\t/', $excelString) > 0) {
            $this->logger->info('Invalid Excel string', ['string' => $excelString]);
            throw new \Exception('Invalid Excel string', 500);
        }

        $values = $this->formatValues(
            $this->splitExcelString($excelString),
            $this->getKeyList(),
        );

        $title       = $this->formatTitle($values);
        $description = $this->formatDescription($values);

        $this->logger->info('Formatted Excel string', [
            'title'       => $title,
            'description' => $description,
        ]);

        return [
            'title'       => $title,
            'description' => $description,
        ];
    }

    private function splitExcelString(string $excelString): array
    {
        $string = str_replace('-x ', '', $excelString);

        return preg_split('/\t/', $string, -1);
    }

    private function formatValues(array $values, array $keys): array
    {
        $valuesCount = count($values);
        $keysCount = count($keys);

        if ($keysCount < $valuesCount) { // Has overflow values
            return $this->handleOverflowValues($values, $keys);
        }

        if ($keysCount > $valuesCount) { // Has missing values
            return $this->handleMissingValues($values, $keys);
        }

        return array_combine($keys, $values);
    }

    private function handleOverflowValues(array $values, array $keys): array
    {
        $keysCount = count($keys);

        // Extract overflow values (everything beyond what we have keys for)
        $overflowStartIndex = $keysCount - 1; // -1 to exclude the overflow key
        $overflowValues = array_slice($values, $overflowStartIndex);
        $filteredOverflowValues = array_filter($overflowValues);

        // Get values that match our keys (excluding overflow)
        $matchingValues = array_slice($values, 0, $keysCount);

        // Combine keys with their matching values
        $result = array_combine($keys, $matchingValues);

        // Add overflow values under special key if any exist
        if (!empty($filteredOverflowValues)) {
            $result[self::OVERFLOW_KEY] = $filteredOverflowValues;
        }

        return $result;
    }

    private function handleMissingValues(array $values, array $keys): array
    {
        $paddedValues = array_pad($values, count($keys), '');

        return array_combine($keys, $paddedValues);
    }


    protected function formatTitle(array $values): string
    {
        return sprintf(self::TITLE_FORMAT,
            DateTimeImmutable::createFromFormat('U', (string)time())->format('Y-m-d'),
            $values['number_url'],
        );
    }

    protected function formatDescription(array $values): string
    {
        $keyValues = $this->getKeyValueList();
        $string = '';

        foreach ($values as $key => $value) {
            if ($key === self::OVERFLOW_KEY && is_array($value)) {
                $string .= "**{$keyValues[$key]}**: \n\n";
                $string .= implode("\n", array_map(fn($value) => "- " . $value . "\n", $value));
                continue;
            }

            $string .= "**{$keyValues[$key]}**: {$value} \n\n";
        }

        return $string;
    }
}
