import React, {createContext, useContext} from 'react';
export const PreviewContext = createContext<any>(null);
export const useAdmin = () => useContext(PreviewContext);
