const offlineBanner = document.querySelector('[data-offline]');

function updateConnection() {
    const offline = !navigator.onLine;
    offlineBanner?.classList.toggle('show', offline);
    document.querySelectorAll('form[method="POST"] button[type="submit"]').forEach(button => {
        button.disabled = offline;
        button.title = offline ? 'برای ثبت عملیات باید آنلاین باشید.' : '';
    });
}

window.addEventListener('online', updateConnection);
window.addEventListener('offline', updateConnection);
updateConnection();

document.addEventListener('submit', event => {
    const form = event.target;
    if (form.method?.toLowerCase() !== 'post') return;
    const button = form.querySelector('button[type="submit"]');
    if (button) {
        button.disabled = true;
        button.textContent = 'در حال ثبت…';
    }
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
}
