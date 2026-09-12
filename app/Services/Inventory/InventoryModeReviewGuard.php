<?php
namespace App\Services\Inventory;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Must run inside the eventual commit transaction, after acquiring inventory write locks. */
class InventoryModeReviewGuard
{
    public function check(string $id,User $actor):object
    {
        if(DB::transactionLevel()<1)throw new \LogicException('Mode review validation requires a transaction.');
        $review=DB::table('inventory_mode_reviews')->where('id',$id)->lockForUpdate()->first();
        abort_unless($review,404);
        if((int)$review->actor_id!==(int)$actor->id)$this->reject('The mode review belongs to another employee.');
        if($review->committed_at!==null)return $review;
        if(CarbonImmutable::parse($review->expires_at,config('app.timezone'))->lessThanOrEqualTo(now()))
            $this->reject('Mode review expired. Review the current inventory again.');
        $current=app(InventoryModeSnapshot::class)->capture();
        if(!hash_equals($review->snapshot_hash,$current['hash']))$this->reject('Inventory changed after review. Review the current inventory again.');
        return $review;
    }

    private function reject(string $message):never
    {
        throw ValidationException::withMessages(['mode'=>$message]);
    }
}
