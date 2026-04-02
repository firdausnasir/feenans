<?php

namespace App\Actions\Imports\Queries;

class DetectImportBankFormatQuery
{
    /**
     * @var array<string, list<string>>
     */
    private const array BANK_HEADER_PATTERNS = [
        'Maybank' => ['Transaction Date', 'Description', 'Debit', 'Credit'],
        'CIMB' => ['Date', 'Description', 'Amount(DR)', 'Amount(CR)'],
        'RHB' => ['Transaction Date', 'Transaction Description', 'Debit Amount', 'Credit Amount'],
        'Public Bank' => ['Date', 'Particulars', 'Withdrawal', 'Deposit'],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const array BANK_MAPPINGS = [
        'Maybank' => [
            'date' => 'Transaction Date',
            'amount' => 'Debit',
            'description' => 'Description',
        ],
        'CIMB' => [
            'date' => 'Date',
            'amount' => 'Amount(DR)',
            'description' => 'Description',
        ],
        'RHB' => [
            'date' => 'Transaction Date',
            'amount' => 'Debit Amount',
            'description' => 'Transaction Description',
        ],
        'Public Bank' => [
            'date' => 'Date',
            'amount' => 'Withdrawal',
            'description' => 'Particulars',
        ],
    ];

    /**
     * @param  list<string>  $headers
     * @return array{detected_bank: ?string, suggested_mapping: ?array<string, string>}
     */
    public function __invoke(array $headers): array
    {
        $normalizedHeaders = array_map(
            static fn (string $header): string => strtolower(trim($header)),
            $headers,
        );

        foreach (self::BANK_HEADER_PATTERNS as $bank => $requiredHeaders) {
            $allFound = true;

            foreach ($requiredHeaders as $requiredHeader) {
                if (! in_array(strtolower($requiredHeader), $normalizedHeaders, true)) {
                    $allFound = false;
                    break;
                }
            }

            if ($allFound) {
                return [
                    'detected_bank' => $bank,
                    'suggested_mapping' => self::BANK_MAPPINGS[$bank] ?? null,
                ];
            }
        }

        return [
            'detected_bank' => null,
            'suggested_mapping' => null,
        ];
    }
}
