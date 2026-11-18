<?php

namespace App\Exceptions;

use RuntimeException;

class GoogleDriveException extends RuntimeException
{
    /** Token dicabut / refresh token invalid: user harus login Google ulang. Jangan di-retry. */
    public static function reauthRequired(string $detail = ''): self
    {
        return new self('Akses Google Drive perlu diotorisasi ulang. '.$detail, 401);
    }
}
