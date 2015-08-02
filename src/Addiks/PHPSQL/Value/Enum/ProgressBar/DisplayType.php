<?php

namespace Addiks\PHPSQL\Value\Enum\ProgressBar;

use Addiks\PHPSQL\Value\Enum;

class DisplayType extends Enum
{
    
    const PERCENT           = "percent";
    const PERCENT_WITH_TIME = "percent with time";
    
    const BAR           = "bar";
    const BAR_WITH_TIME = "bar with time";
}
