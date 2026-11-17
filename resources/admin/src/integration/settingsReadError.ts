/** Safe, read-only diagnostics. Never display server response bodies or credentials. */
export class SettingsReadError extends Error {
 constructor(public source:string,public status?:number,public reason:'network'|'http'|'response'='http'){
  super(reason==='network'?`${source} could not be reached. Check your connection and retry.`:reason==='response'?`${source} returned an unexpected response. Please retry.`:status===401||status===419?`${source} could not load because your session expired (HTTP ${status}). Sign in again, then retry.`:status===403?`${source} could not load because access was denied (HTTP 403). Check your permissions.`:`${source} is temporarily unavailable (HTTP ${status}). Please retry.`);
 }
}
