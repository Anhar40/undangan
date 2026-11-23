<?php

namespace App\Models;

use App\Enums\CoupleOrder;
use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Invitation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'template_id',
        'template_name',
        'template_version',
        'template_path',
        'template_snapshot',
        'cover_photo',
        'template_locked_at',
        'title',
        'slug',
        'status',
        'payment_status',
        'price',
        'preview_started_at',
        'preview_expires_at',
        'activated_at',
        'active_expires_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'payment_status' => PaymentStatus::class,
            'template_snapshot' => 'array',
            'price' => 'decimal:2',
            'template_locked_at' => 'datetime',
            'preview_started_at' => 'datetime',
            'preview_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'active_expires_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function groom(): HasOne
    {
        return $this->hasOne(InvitationGroom::class);
    }

    public function bride(): HasOne
    {
        return $this->hasOne(InvitationBride::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InvitationEvent::class)->orderBy('sort_order');
    }

    public function stories(): HasMany
    {
        return $this->hasMany(InvitationStory::class)->orderBy('sort_order');
    }

    public function families(): HasMany
    {
        return $this->hasMany(InvitationFamily::class)->orderBy('sort_order');
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(InvitationGallery::class)->orderBy('sort_order');
    }

    public function invitationSettings(): HasOne
    {
        return $this->hasOne(InvitationSetting::class);
    }

    public function giftAccounts(): HasMany
    {
        return $this->hasMany(InvitationGiftAccount::class)->orderBy('sort_order');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->latest();
    }

    public function guests(): HasMany
    {
        return $this->hasMany(InvitationGuest::class)->latest();
    }

    public function greetings(): HasMany
    {
        return $this->hasMany(InvitationGreeting::class)->latest();
    }

    public function slugRedirects(): HasMany
    {
        return $this->hasMany(InvitationSlugRedirect::class)->latest();
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', InvitationStatus::Active->value);
    }

    public function scopePreviewExpired($query, ?Carbon $at = null)
    {
        return $query->whereIn('status', [
            InvitationStatus::Draft->value,
            InvitationStatus::Preview->value,
        ])->whereNotNull('preview_expires_at')
            ->where('preview_expires_at', '<=', ($at ?? now())->toDateTimeString());
    }

    public function isPreviewExpired(?Carbon $at = null): bool
    {
        return $this->preview_expires_at !== null
            && ($at ?? now())->greaterThanOrEqualTo($this->preview_expires_at);
    }

    public function previewRemainingSeconds(?Carbon $at = null): int
    {
        if ($this->preview_expires_at === null) {
            return 0;
        }

        return max(0, ($at ?? now())->diffInSeconds($this->preview_expires_at, false));
    }

    public function scopeActiveExpired($query, ?Carbon $at = null)
    {
        return $query->where('status', InvitationStatus::Active->value)
            ->whereNotNull('active_expires_at')
            ->where('active_expires_at', '<=', ($at ?? now())->toDateTimeString());
    }

    public function isActiveExpired(?Carbon $at = null): bool
    {
        return $this->active_expires_at !== null
            && ($at ?? now())->greaterThanOrEqualTo($this->active_expires_at);
    }

    public function activeRemainingSeconds(?Carbon $at = null): int
    {
        if ($this->active_expires_at === null) {
            return 0;
        }

        return max(0, ($at ?? now())->diffInSeconds($this->active_expires_at, false));
    }

    public function hasBeenActivated(): bool
    {
        return $this->activated_at !== null;
    }

    public function isEditable(): bool
    {
        return $this->status?->isEditable() ?? false;
    }

    /**
     * The saved snapshot is frozen against later admin template edits.
     * Customers may still deliberately replace the template on an active
     * invitation through TemplateSwitchService; that writes a new snapshot.
     */
    public function templateIsLocked(): bool
    {
        return $this->template_locked_at !== null || $this->activated_at !== null;
    }

    public function publicUrl(): string
    {
        return route('invitation.show', $this->slug);
    }

    /** Human-readable couple names for dashboard tables (PRD 12.2). */
    public function coupleLabel(): string
    {
        // Legacy invitations keep the historical groom-first dashboard label.
        // An explicit setting makes dashboard, share text and the invitation
        // itself agree on the same order.
        $order = $this->invitationSettings?->couple_order;
        $names = $order instanceof CoupleOrder && $order->isFemaleFirst()
            ? [$this->bride?->full_name, $this->groom?->full_name]
            : [$this->groom?->full_name, $this->bride?->full_name];

        $names = array_values(array_filter($names));

        return $names === [] ? $this->title : implode(' & ', $names);
    }

    /** First event date for dashboard tables (PRD 12.2). */
    public function eventDateLabel(): string
    {
        $event = $this->events->first();

        return $event?->event_date?->locale('id')->translatedFormat('d M Y') ?? 'Belum diatur';
    }

    /** Snapshotted template capabilities, never affected by later admin edits. */
    public function templateFeatures(): array
    {
        $default = array_fill_keys(Template::FEATURES, false);

        $snapshot = is_array($this->template_snapshot) ? $this->template_snapshot : [];
        $features = isset($snapshot['features']) && is_array($snapshot['features'])
            ? $snapshot['features']
            : [];

        // Never read live template features: a created invitation keeps the
        // capabilities frozen at creation time (PRD 4.4).
        return array_merge($default, $features);
    }

    public function templateConfig(): array
    {
        $snapshot = is_array($this->template_snapshot) ? $this->template_snapshot : [];

        return isset($snapshot['config']) && is_array($snapshot['config'])
            ? $snapshot['config']
            : [];
    }

    public function supports(string $feature): bool
    {
        return (bool) ($this->templateFeatures()[$feature] ?? false);
    }
}
