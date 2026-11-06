<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentScholarship extends Model
{
    use HasFactory;

    public const TYPES = [
        'merit' => 'Merit Scholarship',
        'need_based' => 'Need-Based Scholarship',
        'special' => 'Special Scholarship',
        'staff_child' => 'Staff Child Discount',
        'sibling' => 'Sibling Discount',
        'freedom_fighter' => 'Freedom Fighter Quota',
        'special_waiver' => 'Special Fee Waiver',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'pending' => 'Pending Approval',
        'approved' => 'Approved',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'expired' => 'Expired',
        'rejected' => 'Rejected',
    ];

    public const FREQUENCIES = [
        'once' => 'One-Time',
        'monthly' => 'Monthly',
        'per_invoice' => 'Per Fee Invoice',
        'academic_year' => 'Entire Academic Year',
    ];

    public const COMBINE_POLICIES = [
        'no_combine' => 'Do Not Combine',
        'highest_only' => 'Highest Discount Only',
        'different_categories' => 'Combine Different Scholarships on Different Fee Categories',
        'approved_stacking' => 'Allow Approved Stacking',
    ];

    protected $fillable = [
        'student_id', 'academic_session_id', 'scholarship_name', 'scholarship_type',
        'discount_method', 'discount_value', 'applies_to', 'discount_frequency',
        'maximum_discount_amount', 'start_date', 'end_date', 'status',
        'description', 'reason', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'maximum_discount_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentScholarshipItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(ScholarshipInvoiceAdjustment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->scholarship_type] ?? ucfirst($this->scholarship_type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function isApplicableTo(\App\Models\StudentFeeAssignment $assignment, ?string $onDate = null): bool
    {
        $date = $onDate ?? ($assignment->due_date?->toDateString() ?? now()->toDateString());

        if ($this->status !== 'active' && $this->status !== 'approved') {
            return false;
        }

        if ((int) $this->academic_session_id !== (int) $assignment->academic_session_id) {
            return false;
        }

        if ((int) $this->student_id !== (int) $assignment->student_id) {
            return false;
        }

        if ($date < $this->start_date->toDateString() || $date > $this->end_date->toDateString()) {
            return false;
        }

        if ($this->applies_to === 'selected') {
            return $this->items->contains('fee_category_id', $assignment->fee_category_id);
        }

        return true;
    }

    /**
     * Compute the discount this scholarship grants against a given fee row.
     * Capped by the row's gross (minus structure discount) and the scholarship
     * maximum when set. Returns 0 when the scholarship does not apply.
     */
    public function discountFor(\App\Models\StudentFeeAssignment $assignment, ?string $onDate = null): float
    {
        if (! $this->isApplicableTo($assignment, $onDate)) {
            return 0.0;
        }

        $item = $this->applies_to === 'selected'
            ? $this->items->firstWhere('fee_category_id', $assignment->fee_category_id)
            : null;

        $type = $item ? $item->discount_type : $this->discount_method;
        $value = (float) ($item ? $item->discount_value : $this->discount_value);
        $gross = max(0, (float) $assignment->amount - (float) $assignment->discount_amount);

        $conceded = FeeStructureItem::concessionAmount($value, $type, $gross);

        if ($this->maximum_discount_amount !== null && (float) $this->maximum_discount_amount > 0) {
            $conceded = min($conceded, (float) $this->maximum_discount_amount);
        }

        return $conceded;
    }

    public function discountForFeeCategory(int $feeCategoryId, float $gross): float
    {
        $item = $this->applies_to === 'selected'
            ? $this->items->firstWhere('fee_category_id', $feeCategoryId)
            : null;

        if ($this->applies_to === 'selected' && ! $item) {
            return 0.0;
        }

        $type = $item ? $item->discount_type : $this->discount_method;
        $value = (float) ($item ? $item->discount_value : $this->discount_value);

        return FeeStructureItem::concessionAmount($value, $type, $gross);
    }
}
