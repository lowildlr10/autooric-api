<?php

namespace App\Services;

use App\Models\UserLog;

class UserLogsServices
{
    public function __construct(
        string $userId,
        string $ipAddress,
        string $userAgent,
    ) {
        $this->user_id = $userId;
        $this->ip_address = $ipAddress;
        $this->user_agent = $userAgent;
    }

    public function logActivity(string $activity) {
        try {
            UserLog::create([
                'user_id' => $this->user_id,
                'ip_address' => $this->ip_address,
                'user_agent' => $this->user_agent,
                'activity' => $activity
            ]);
        } catch (\Throwable $th) {}
    }
}
