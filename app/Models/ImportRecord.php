<?php

namespace App\Models;

use Database\Factories\ImportRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRecord extends Model
{
    /** @use HasFactory<ImportRecordFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'imports';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ledger_id',
        'filename',
        'row_count',
        'imported_count',
        'skipped_count',
        'mapping_used',
        'imported_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapping_used' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
