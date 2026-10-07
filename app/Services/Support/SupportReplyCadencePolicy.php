<?php
namespace App\Services\Support;

class SupportReplyCadencePolicy
{
    public function settings(array $settings):array
    {
        $c=$settings['replyCadence']??[];
        return ['enabled'=>($c['enabled']??false)===true,
            'collectionSeconds'=>max(0,min(3,(int)($c['collectionSeconds']??2))),
            'intervalSeconds'=>max(0,min(3,(int)($c['intervalSeconds']??1))),
            'maxSegments'=>max(1,min(3,(int)($c['maxSegments']??3)))];
    }
}
