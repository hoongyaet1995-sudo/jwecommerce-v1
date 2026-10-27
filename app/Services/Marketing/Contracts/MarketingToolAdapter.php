<?php
namespace App\Services\Marketing\Contracts;
/** Adapter receives a server-bound task envelope, never client scripts or a domain model. */
interface MarketingToolAdapter {
 public function execute(array $task):array;
 public function verify(array $output):void;
}
