<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Activity log per-tenant: otomatis ber-organization_id (dari user yang aksi)
 * dan ter-scope ke organisasi user yang login (lewat trait BelongsToOrganization).
 */
class ActivityLog extends SpatieActivity
{
    use BelongsToOrganization;
}
