/** Keep Lazada's provider code intact for API and ledger operations. */
export function warehouseDisplayCode(code: string | null | undefined): string {
  return code === 'dropshipping' ? 'Default Warehouse' : code ?? '';
}
