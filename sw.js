const CACHE_NAME = 'hyundai-link-v1';
const ASSETS = [
  'index.php',
  'manifest.json',
  'icon.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS);
    })
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // API 請求使用 Network First 策略，確保獲取最新數據
  if (url.pathname.includes('/api/')) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          // 成功獲取，返回最新數據
          return response;
        })
        .catch(() => {
          // 網路失敗時使用快取（離線模式）
          return caches.match(event.request);
        })
    );
    return;
  }

  // 其他靜態資源使用 Cache First 策略
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});
