import type { LaravelAdminBootstrap } from '../types';

export const submitLaravelLogout = (
  bootstrap: LaravelAdminBootstrap,
  ownerDocument: Document = document,
): void => {
  const form = ownerDocument.createElement('form');
  form.method = 'post';
  form.action = bootstrap.logoutUrl;
  form.style.display = 'none';

  const token = ownerDocument.createElement('input');
  token.type = 'hidden';
  token.name = '_token';
  token.value = bootstrap.csrfToken;
  form.appendChild(token);
  ownerDocument.body.appendChild(form);
  form.submit();
};
