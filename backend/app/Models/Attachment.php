<?php

namespace App\Models;

use App\Enums\AttachmentDocumentType;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'document_type' => AttachmentDocumentType::class,
            'is_signed_copy' => 'boolean',
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requiredPermission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'required_permission_id');
    }
}
