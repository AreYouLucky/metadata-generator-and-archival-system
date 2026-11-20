<?php

namespace App\Enums;

/**
 * Licence under which a video may be used.
 */
enum Licence: string
{
    case AllRightsReserved = 'all_rights_reserved';
    case CcBy = 'cc_by';
    case CcBySa = 'cc_by_sa';
    case CcByNc = 'cc_by_nc';
    case CcByNcSa = 'cc_by_nc_sa';
    case CcByNd = 'cc_by_nd';
    case CcByNcNd = 'cc_by_nc_nd';
    case Cc0 = 'cc0';
    case PublicDomain = 'public_domain';
    case Custom = 'custom';
}
