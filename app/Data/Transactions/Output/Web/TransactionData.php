<?php

namespace App\Data\Transactions\Output\Web;

use App\Data\Accounts\Output\Web\AccountData;
use App\Data\Categories\Output\CategoryData;
use App\Data\Payees\Output\PayeeData;
use App\Data\Shared\Output\BaseOutputData;
use App\Data\Tags\Output\TagData;
use App\Enums\TransactionType;
use App\Models\Attachment;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use Spatie\LaravelData\Optional;

class TransactionData extends BaseOutputData
{
    /**
     * @param  array<int, array<string, mixed>>|Optional  $tags
     * @param  array<int, array<string, mixed>>|Optional  $splits
     * @param  array<int, array<string, mixed>>|Optional  $attachments
     */
    public function __construct(
        public int $id,
        public int $ledger_id,
        public int $account_id,
        public ?int $category_id,
        public ?int $payee_id,
        public ?int $bill_id,
        public string $transaction_type,
        public string $amount,
        public ?string $description,
        public ?string $notes,
        public ?string $transaction_date,
        public ?string $transfer_pair_id,
        public int|Optional $splits_count,
        public bool $is_split,
        public int|Optional $attachments_count,
        public array|Optional|null $account,
        public array|Optional|null $category,
        public array|Optional|null $payee,
        public array|Optional $tags,
        public array|Optional $splits,
        public array|Optional $attachments,
        public array|Optional|null $transfer_pair,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Transaction $transaction, bool $includeTransferPair = true): self
    {
        $attributes = $transaction->getAttributes();
        $splitsCount = array_key_exists('splits_count', $attributes)
            ? (int) $attributes['splits_count']
            : Optional::create();
        $attachmentsCount = array_key_exists('attachments_count', $attributes)
            ? (int) $attributes['attachments_count']
            : Optional::create();

        return new self(
            id: $transaction->id,
            ledger_id: $transaction->ledger_id,
            account_id: $transaction->account_id,
            category_id: $transaction->category_id,
            payee_id: $transaction->payee_id,
            bill_id: $transaction->bill_id,
            transaction_type: $transaction->transaction_type instanceof TransactionType
                ? $transaction->transaction_type->value
                : (string) $transaction->transaction_type,
            amount: (string) $transaction->amount,
            description: $transaction->description,
            notes: $transaction->notes,
            transaction_date: $transaction->transaction_date?->toDateString(),
            transfer_pair_id: $transaction->transfer_pair_id,
            splits_count: $splitsCount,
            is_split: array_key_exists('splits_count', $attributes)
                ? (int) $attributes['splits_count'] > 0
                : ($transaction->relationLoaded('splits') && $transaction->splits->isNotEmpty()),
            attachments_count: $attachmentsCount,
            account: $transaction->relationLoaded('account')
                ? ($transaction->account !== null ? AccountData::fromModel($transaction->account)->toArray() : null)
                : Optional::create(),
            category: $transaction->relationLoaded('category')
                ? ($transaction->category !== null ? CategoryData::fromModel($transaction->category)->toArray() : null)
                : Optional::create(),
            payee: $transaction->relationLoaded('payee')
                ? ($transaction->payee !== null ? PayeeData::fromModel($transaction->payee)->toArray() : null)
                : Optional::create(),
            tags: $transaction->relationLoaded('tags')
                ? $transaction->tags
                    ->map(fn ($tag) => TagData::fromModel($tag)->toArray())
                    ->values()
                    ->all()
                : Optional::create(),
            splits: $transaction->relationLoaded('splits')
                ? $transaction->splits
                    ->map(fn (TransactionSplit $split) => self::splitToArray($split))
                    ->values()
                    ->all()
                : Optional::create(),
            attachments: $transaction->relationLoaded('attachments')
                ? $transaction->attachments
                    ->map(fn (Attachment $attachment) => self::attachmentToArray($attachment))
                    ->values()
                    ->all()
                : Optional::create(),
            transfer_pair: $includeTransferPair && $transaction->relationLoaded('transferPair')
                ? ($transaction->transferPair !== null ? self::fromModel($transaction->transferPair, false)->toArray() : null)
                : Optional::create(),
            created_at: $transaction->created_at?->toIso8601String(),
            updated_at: $transaction->updated_at?->toIso8601String(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function splitToArray(TransactionSplit $split): array
    {
        return [
            'id' => $split->id,
            'transaction_id' => $split->transaction_id,
            'category_id' => $split->category_id,
            'payee_id' => $split->payee_id,
            'amount' => (string) $split->amount,
            'description' => $split->description,
            'category' => $split->relationLoaded('category')
                ? ($split->category !== null ? CategoryData::fromModel($split->category)->toArray() : null)
                : null,
            'payee' => $split->relationLoaded('payee')
                ? ($split->payee !== null ? PayeeData::fromModel($split->payee)->toArray() : null)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function attachmentToArray(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'transaction_id' => $attachment->transaction_id,
            'filename' => $attachment->filename,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'url' => $attachment->url,
        ];
    }
}
