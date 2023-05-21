<?php
declare(strict_types=1);

namespace vavo\Common;

enum HydrationMode : string
{
    case OBJECT = 'object';
    case ARRAY = 'array';
}
