<?php

namespace App\Enums;

/**
 * Cast and production roles credited on a record.
 */
enum ContributorRole: string
{
    case Producer = 'producer';
    case Director = 'director';
    case Videographer = 'videographer';
    case Editor = 'editor';
    case Presenter = 'presenter';
    case Cast = 'cast';
    case Contributor = 'contributor';
}
