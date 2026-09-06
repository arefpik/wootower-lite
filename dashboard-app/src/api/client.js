const REST_NAMESPACE = 'wootower/v1';

function getRuntimeConfig() {
  return window.wootowerDashboardConfig || {};
}

/**
 * All REST calls from the dashboard go through this single client so auth
 * headers and error handling stay in one place, instead of scattered fetch()
 * calls across components.
 */
export async function apiGet(path) {
  const { restUrl, nonce } = getRuntimeConfig();

  const response = await fetch(`${restUrl}${REST_NAMESPACE}/${path}`, {
    headers: {
      'X-WP-Nonce': nonce || '',
    },
  });

  if (!response.ok) {
    throw new Error(`WooTower API request to "${path}" failed with status ${response.status}.`);
  }

  return response.json();
}
