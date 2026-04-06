<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'filename',
        'path',
        'mime_type',
        'size',
    ];

    /**
     * The appended accessors.
     *
     * @var list<string>
     */
    protected $appends = [
        'url',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @param  iterable<string>  $paths
     */
    public static function deleteFiles(iterable $paths): bool
    {
        $filteredPaths = collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();

        if ($filteredPaths === []) {
            return true;
        }

        return Storage::disk((string) config('app.attachment_disk', 'local'))->delete($filteredPaths);
    }

    public function getUrlAttribute(): string
    {
        return route('ledgers.transactions.attachments.show', [
            'ledger' => $this->transaction->ledger_id,
            'transaction' => $this->transaction_id,
            'attachment' => $this->id,
        ]);
    }
}
