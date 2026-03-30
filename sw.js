const CACHE_NAME = 'hyundai-link-v3';
const ASSETS = [
  'https://nx4link.inskychen.com/index.php',
  'https://nx4link.inskychen.com/manifest.json',
  'https://nx4link.inskychen.com/icon.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS);
    })
  );
});

self.addEventListener('fetch', (event) => {
  // 只處理同源請求，外部 API 直接放行
  if (!event.request.url.startsWith(self.location.origin)) return;

  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});
