<?php

namespace App\Exceptions;

use RuntimeException;

class UnbalancedInvoiceAllocation extends RuntimeException
{
    public const MESSAGE = 'Cost centre allocation must be balanced before finalising this invoice. Resolve the Shortfall or Unallocated amount in the allocation panel.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
