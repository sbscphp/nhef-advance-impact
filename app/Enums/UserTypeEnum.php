<?php

namespace App\Enums;

enum UserTypeEnum: string
{
    case CUSTOMER = 'CUSTOMER';
    case ADMIN = 'ADMIN';

    /** Unauthenticated donor (BRD ENT-04); no account, identified by name/email only. */
    case GUEST = 'GUEST';
}
