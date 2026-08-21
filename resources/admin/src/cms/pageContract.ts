export type ContractFieldValue = object | string | number | boolean | null | undefined;

export function hasOwnContractField(value: object, key: string): boolean {
  return Object.prototype.hasOwnProperty.call(value, key);
}
