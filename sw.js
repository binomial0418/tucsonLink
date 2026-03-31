const CACHE_NAME = 'hyundai-link-v5';
const STATIC_ASSETS = [
  'https://nx4link.inskychen.com/manifest.json',
  'https://nx4link.inskychen.com/icon.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    })
  );
});

self.addEventListener('fetch', (event) => {
  // 只處理同源請求，外部 API 直接放行
  if (!event.request.url.startsWith(self.location.origin)) return;

  // POST 等非 GET 請求（如登入表單）直接放行，不走快取
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // PHP 頁面（index.php、api/*.php）含有 session 邏輯，永遠走網路
  if (url.pathname.endsWith('.php')) return;

  // 靜態資產才走 cache-first
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});
