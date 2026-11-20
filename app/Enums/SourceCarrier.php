<?php

namespace App\Enums;

/**
 * Physical or digital carrier a record was transferred from.
 */
enum SourceCarrier: string
{
    case BornDigital = 'born_digital';
    case CameraCard = 'camera_card';
    case Digibeta = 'digibeta';
    case BetacamSp = 'betacam_sp';
    case Hdcam = 'hdcam';
    case Dvcam = 'dvcam';
    case Minidv = 'minidv';
    case Umatic = 'umatic';
    case Vhs = 'vhs';
    case Film16mm = 'film_16mm';
    case Film35mm = 'film_35mm';
    case Other = 'other';
}
