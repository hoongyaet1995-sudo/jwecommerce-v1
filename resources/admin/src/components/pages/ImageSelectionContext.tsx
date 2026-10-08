import React from 'react';
export const ImageSelectionContext=React.createContext<{editing:boolean;selected?:string;select?:(target:string)=>void}>({editing:false});
