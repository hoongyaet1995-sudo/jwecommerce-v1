<?php
namespace App\Models;
/** Separate generation ledger; instant posts never create an Automation configuration. */
class MarketingInstantPostRun extends MarketingAutomationRun {protected $table='marketing_instant_post_runs';}
