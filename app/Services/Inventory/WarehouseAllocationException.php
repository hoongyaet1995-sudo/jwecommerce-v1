<?php
namespace App\Services\Inventory;

/** Business stock/ownership failure, distinct from persistence or transport failures. */
class WarehouseAllocationException extends \RuntimeException {}
