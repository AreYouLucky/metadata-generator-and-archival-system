<?php

namespace App\Enums;

/**
 * Shots are detected automatically; scenes are user-defined groups of shots.
 */
enum SceneKind: string
{
    case Shot = 'shot';
    case Scene = 'scene';
}
