<?php
namespace App\Support;
class AutomationResponseSupport
{
    public function available(): bool
    {
        return function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
    }
}
