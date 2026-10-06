<?php
namespace App\Services\Customers;
use App\Models\{Customer,CustomerPlatformPresence};
class CustomerPlatformService {
 public function record(Customer $customer,string $platform):void {
  $presence=CustomerPlatformPresence::firstOrCreate(['customer_id'=>$customer->id,'platform'=>$platform],['first_seen_at'=>now(),'last_seen_at'=>now()]);
  $presence->update(['last_seen_at'=>now()]);
 }
}
