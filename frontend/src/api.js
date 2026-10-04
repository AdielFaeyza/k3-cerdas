const BASE = import.meta.env.VITE_API_URL;
export const TOKEN = import.meta.env.VITE_API_TOKEN;

async function request(method, path, body) {
  const res = await fetch(BASE + path, {
    method,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${TOKEN}` },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || `HTTP ${res.status}`);
  }
  return res.json();
}

export const api = {
  get: (p) => request('GET', p),
  post: (p, b) => request('POST', p, b),
};
