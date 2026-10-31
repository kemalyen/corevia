<?php

namespace App\Enums;

enum OrderOrigin: string
{
    case ERP_IMPORT = 'erp_import';
    case PORTAL = 'portal';
}
