<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Ledger;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class TransactionCategoryGuesser
{
    public function guess(
        Ledger $ledger,
        TransactionType $transactionType,
        string $description,
        float $amount,
        string $accountName,
    ): ?int {
        $apiKey = config('services.deepseek.key');

        if (! is_string($apiKey) || $apiKey === '') {
            return null;
        }

        $categories = $ledger->categories()
            ->with('parent:id,name')
            ->where('transaction_type', $transactionType->value)
            ->orderBy('parent_id')
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        if ($categories->isEmpty()) {
            return null;
        }

        $allowedCategoryIds = $categories->pluck('id')->all();

        try {
            $response = Http::baseUrl((string) config('services.deepseek.base_url'))
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(10)
                ->retry(2, 200)
                ->post('/chat/completions', [
                    'model' => config('services.deepseek.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => json_encode([
                            'transaction' => [
                                'type' => $transactionType->value,
                                'description' => $description,
                                'amount' => $amount,
                                'account' => $accountName,
                                'currency' => $ledger->currency_code,
                            ],
                            'allowed_categories' => $categories
                                ->map(fn ($category): array => [
                                    'id' => $category->id,
                                    'name' => $category->name,
                                    'parent' => $category->parent?->name,
                                ])
                                ->values()
                                ->all(),
                        ], JSON_THROW_ON_ERROR)],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0,
                    'max_tokens' => 180,
                    'stream' => false,
                    'thinking' => ['type' => 'disabled'],
                ])
                ->throw();
        } catch (RequestException $exception) {
            report($exception);

            return null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || $content === '') {
            return null;
        }

        try {
            $guess = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! is_array($guess)) {
            return null;
        }

        $categoryId = $guess['category_id'] ?? null;
        $confidence = (float) ($guess['confidence'] ?? 0);

        if ($categoryId === null || $confidence < 0.65) {
            return null;
        }

        $categoryId = (int) $categoryId;

        return in_array($categoryId, $allowedCategoryIds, true)
            ? $categoryId
            : null;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You categorize personal finance transactions for a ledger.

Return exactly one JSON object and no other text:
{"category_id": integer|null, "confidence": number, "reason": string}

Rules:
- Choose category_id only from allowed_categories. Never invent a category.
- Use only the allowed categories already filtered for the transaction type.
- Prefer the most specific child category when it clearly matches the merchant or description.
- Use a parent category only when no child category is a clearer fit.
- If the description is vague, ambiguous, or does not clearly fit an allowed category, return category_id null.
- Set confidence from 0 to 1. Use at least 0.75 only for clear matches, and below 0.65 for uncertain matches.
- The reason must be short and must not mention unavailable categories.
PROMPT;
    }
}
