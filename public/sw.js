const CACHE='paytakht-static-v12';
const STATIC_ASSETS=['/manifest.webmanifest','/icons/pk-icon.svg','/icons/pk-icon-192.png','/icons/pk-icon-512.png','/icons/apple-touch-icon.png','/assets/app.css','/assets/app.js'];

self.addEventListener('install',event=>event.waitUntil(
    caches.open(CACHE).then(cache=>cache.addAll(STATIC_ASSETS)).then(()=>self.skipWaiting())
));

self.addEventListener('activate',event=>event.waitUntil(
    caches.keys()
        .then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key))))
        .then(()=>self.clients.claim())
));

self.addEventListener('fetch',event=>{
    if(event.request.method!=='GET')return;

    const url=new URL(event.request.url);
    if(url.origin!==self.location.origin)return;

    if(STATIC_ASSETS.includes(url.pathname)){
        event.respondWith(caches.match(event.request).then(cached=>cached||fetch(event.request)));
        return;
    }

    // Authenticated HTML and business data are deliberately network-only.
    // Never persist a dashboard, invoice, report, or private response in Cache Storage.
    if(event.request.mode==='navigate'){
        event.respondWith(fetch(event.request).catch(()=>new Response(
            '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>آفلاین</title><body><main><h1>اتصال در دسترس نیست</h1><p>ثبت عملیات فروشگاه فقط در حالت آنلاین انجام می‌شود. پس از برقراری اینترنت دوباره تلاش کنید.</p></main></body></html>',
            {status:503,headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}}
        )));
    }
});
