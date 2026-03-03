<?php

namespace App\Contracts;

use App\Support\ContentFilterResult;

interface ContentFilter
{
    public function inspect(string $content, string $context = 'default'): ContentFilterResult;
}
