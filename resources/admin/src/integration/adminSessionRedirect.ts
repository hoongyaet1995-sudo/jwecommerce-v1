type LocationAdapter = Pick<Location, 'pathname' | 'search' | 'hash' | 'replace'>;
type FetchAdapter = typeof window.fetch;

const isAdminApiRequest = (input: RequestInfo | URL, origin: string): boolean => {
  const url = typeof input === 'string'
    ? new URL(input, origin)
    : input instanceof URL
      ? input
      : new URL(input.url, origin);

  return url.origin === origin && url.pathname.startsWith('/admin/api/');
};

export const installAdminSessionRedirect = (
  fetcher: FetchAdapter = window.fetch.bind(window),
  location: LocationAdapter = window.location,
): FetchAdapter => {
  let redirecting = false;
  const origin = typeof window === 'undefined' ? 'http://localhost' : window.location.origin;

  return async (input, init) => {
    const response = await fetcher(input, init);

    if (!redirecting && isAdminApiRequest(input, origin) && (response.status === 401 || response.status === 419)) {
      redirecting = true;
      const intended = `${location.pathname}${location.search}${location.hash}`;
      location.replace(`/admin/login?intended=${encodeURIComponent(intended)}`);
    }

    return response;
  };
};
