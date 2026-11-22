<?php

namespace App\Enums;

/**
 * State of a batch's ZIP of exports (BuildBatchZipJob).
 */
enum BatchZipStatus: string
{
    case Building = 'building';
    case Ready = 'ready';
    case Failed = 'failed';
}
