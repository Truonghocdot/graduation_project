<?php

namespace App\Enums;

enum RatingModerationStatus: string
{
    case Visible = 'VISIBLE';
    case Hidden = 'HIDDEN';
    case Flagged = 'FLAGGED';
}
