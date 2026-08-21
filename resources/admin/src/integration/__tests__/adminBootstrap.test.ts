import { describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { readAdminBootstrap } from '../adminBootstrap';
import { submitLaravelLogout } from '../laravelSession';

const bootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  dashboardUrl: '/admin/api/dashboard',
  customersUrl: '/admin/api/customers',
};

describe('Laravel Admin bootstrap adapter', () => {
  it('reads the Laravel identity and logout endpoint', () => {
    expect(readAdminBootstrap(bootstrap)).toEqual(expect.objectContaining({
      user: bootstrap.user,
      csrfToken: 'csrf-token',
      logoutUrl: '/logout',
      dashboardUrl: '/admin/api/dashboard',
      customersUrl: '/admin/api/customers',
    }));
  });

  it('rejects a missing Laravel Admin bootstrap', () => {
    expect(() => readAdminBootstrap(undefined)).toThrow('Missing Laravel Admin bootstrap');
  });

  it('submits the real logout endpoint with the Laravel CSRF token', () => {
    const submit = vi.fn();
    const form = {
      method: '',
      action: '',
      style: {},
      appendChild: vi.fn(),
      submit,
    } as unknown as HTMLFormElement;
    const input = {} as HTMLInputElement;
    const logoutDocument = {
      createElement: vi.fn()
        .mockReturnValueOnce(form)
        .mockReturnValueOnce(input),
      body: { appendChild: vi.fn() },
    } as unknown as Document;

    submitLaravelLogout(bootstrap, logoutDocument);

    expect(form.method).toBe('post');
    expect(form.action).toContain('/logout');
    expect(input.type).toBe('hidden');
    expect(input.name).toBe('_token');
    expect(input.value).toBe('csrf-token');
    expect(logoutDocument.body.appendChild).toHaveBeenCalledWith(form);
    expect(form.appendChild).toHaveBeenCalledWith(input);
    expect(submit).toHaveBeenCalledOnce();
  });

  it('leaves Laravel login to the server instead of mounting the source modal', () => {
    const app = readFileSync(resolve(process.cwd(), 'src/App.tsx'), 'utf8');

    expect(app).not.toContain('<AdminLoginModal />');
  });
});
