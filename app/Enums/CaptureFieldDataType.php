<?php

namespace App\Enums;

enum CaptureFieldDataType: string
{
    case Number = 'number';
    case Integer = 'integer';
    case Text = 'text';
    case Date = 'date';
    case Time = 'time';
    case Boolean = 'boolean';
    case Select = 'select';
}
