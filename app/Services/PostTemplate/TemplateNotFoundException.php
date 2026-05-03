<?php

declare(strict_types=1);

namespace App\Services\PostTemplate;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TemplateNotFoundException extends NotFoundHttpException
{
    public function __construct(string $slug)
    {
        parent::__construct("Post template '{$slug}' not found in any locale.");
    }
}
