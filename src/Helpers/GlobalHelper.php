<?php

namespace App\Helpers;

use Exception;
use Psr\Http\Message\ServerRequestInterface;

class GlobalHelper
{
    public static bool $outLoggerStatus = true;
    public static string $outLoggerLink = "http://127.0.0.1/logger.php";


    public static function getCurrentMicroTime(): float
    {
        return round(microtime(true) * 1000);
    }

    public static function generateRandomOtp(): float
    {
        return rand(11111, 99999);
    }

    public static function parseClientIp(array $serverParams): string
    {
        if (!empty($serverParams['HTTP_CLIENT_IP'])) {
            return $serverParams['HTTP_CLIENT_IP'];
        }
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            return $serverParams['HTTP_X_FORWARDED_FOR'];
        }
        return $serverParams['REMOTE_ADDR'];
    }
}