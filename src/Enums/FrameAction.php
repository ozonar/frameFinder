<?php
namespace App\Enums;

enum FrameAction
{
    case SplitHorisontal;
    case SplitVertical;
    case CombineToNext;
    case Remove;
}