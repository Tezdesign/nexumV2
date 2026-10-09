<?php

namespace App\Controller\Trait;

trait LocalRedirectTrait
{
    /**
     * Returns the path when it is a same site path ("/task"), or '' otherwise.
     * Rejects "//host" and "/\host", which browsers treat as another site.
     */
    protected function localPath(?string $path): string
    {
        $path = (string) $path;

        return preg_match('~^/(?![/\\\\])[^\x00-\x1f\\\\]*$~', $path) === 1 ? $path : '';
    }
}
