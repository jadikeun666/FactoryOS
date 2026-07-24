<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @see docs/exports.md § Export Guard
 *
 * Dilempar saat export diminta untuk data yang belum memenuhi syarat
 * (mis. Schedule tanpa assignments, MrpRun tanpa requirements).
 */
class ExportNotAllowedException extends HttpException
{
    public function __construct(string $reason)
    {
        parent::__construct(403, "Export tidak diizinkan: {$reason}");
    }
}