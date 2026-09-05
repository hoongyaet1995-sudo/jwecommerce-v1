import { useAdmin } from './AdminContext';
import { currencyFor, formatAdminMoney } from '../integration/adminMoney';

export function useAdminCurrency() {
  const { currenciesSetting = [] } = useAdmin();
  return {
    currencyCode: currencyFor(currenciesSetting)?.code ?? '',
    money: (amount: number | null | undefined, code?: string, rate = 1) => formatAdminMoney(amount, currenciesSetting, code, rate),
  };
}
