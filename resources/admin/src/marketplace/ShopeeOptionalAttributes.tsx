import React from 'react';
import {ShopeeAttributeSections} from './ShopeePublishingRequirements';
import type {useShopeePublishingRequirements} from './useShopeePublishingRequirements';

export function ShopeeOptionalAttributes({review}:{review:ReturnType<typeof useShopeePublishingRequirements>}){
 return <ShopeeAttributeSections review={review} onlyOptional/>;
}
