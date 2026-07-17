<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    protected static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->logActivity('Created');
        });

        static::updated(function ($model) {
            $model->logActivity('Updated');
        });

        static::deleted(function ($model) {
            $model->logActivity('Deleted');
        });
    }

    public function logActivity(string $action)
    {
        // Get changes if updated
        $properties = [];
        if ($action === 'Updated') {
            $properties = [
                'old' => array_intersect_key($this->getRawOriginal(), $this->getDirty()),
                'new' => $this->getDirty(),
            ];
        }

        ActivityLog::create([
            'organization_id' => $this->organization_id ?? auth()->user()?->organization_id,
            'user_id' => auth()->id(),
            'action' => $action . ' ' . class_basename($this),
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'description' => auth()->user()?->first_name . ' ' . $action . ' ' . class_basename($this) . ' #' . $this->id,
            'properties' => $properties,
            'ip_address' => Request::ip(),
        ]);
    }
}
