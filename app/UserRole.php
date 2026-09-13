<?php

namespace App;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case TourLeader = 'tour_leader';

    public function homeRouteName(): string
    {
        return match ($this) {
            self::SuperAdmin => 'super-admin.index',
            self::TourLeader => 'tour-leader.index',
        };
    }
}
