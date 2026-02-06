<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedDocument extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<DocumentTemplate, GeneratedDocument>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    /**
     * @return BelongsTo<User, GeneratedDocument>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
