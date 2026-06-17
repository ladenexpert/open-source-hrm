<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\LeaveRequestAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LeaveRequestAttachment extends Model
{
    /** @use HasFactory<LeaveRequestAttachmentFactory> */
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'leave_request_id',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'leave_request_id' => 'integer',
        'size_bytes' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    protected static function newFactory(): LeaveRequestAttachmentFactory
    {
        return LeaveRequestAttachmentFactory::new();
    }
}
