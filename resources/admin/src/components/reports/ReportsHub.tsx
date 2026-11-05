import React from 'react';
import {useAdmin} from '../../context/AdminContext';
import {SalesBusinessReport} from './SalesBusinessReport';
import {OperationalBusinessReport} from './OperationalBusinessReport';
import {ReportStaffAccess} from './ReportStaffAccess';
import {ReportTabs} from './ReportTabs';
export const ReportsHub:React.FC=()=>{
 const {currentSection}=useAdmin();
 const requested=currentSection.replace('reports-','');
 const type=['sales','orders','products','customers','shipping','tax','marketing'].includes(requested)?requested:'sales';
 const navigation=<ReportTabs active={type}/>;
 return <div className="space-y-6">{type==='sales'?<SalesBusinessReport key="sales" navigation={navigation}/>:type==='tax'?<SalesBusinessReport key="financial" financial navigation={navigation}/>:<OperationalBusinessReport key={type} type={type as 'orders'|'products'|'customers'|'shipping'|'marketing'} navigation={navigation}/>}<ReportStaffAccess/></div>;
};
