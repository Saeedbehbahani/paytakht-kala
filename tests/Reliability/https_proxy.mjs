import fs from 'node:fs';
import http from 'node:http';
import https from 'node:https';

const [keyPath, certPath, listenPort = '8443', upstreamPort = '8011'] = process.argv.slice(2);

if (!keyPath || !certPath) {
  throw new Error('Usage: node https_proxy.mjs <key> <cert> [listenPort] [upstreamPort]');
}

const server = https.createServer({
  key: fs.readFileSync(keyPath),
  cert: fs.readFileSync(certPath),
}, (request, response) => {
  const upstream = http.request({
    hostname: '127.0.0.1',
    port: Number(upstreamPort),
    method: request.method,
    path: request.url,
    headers: {
      ...request.headers,
      host: `127.0.0.1:${upstreamPort}`,
      'x-forwarded-proto': 'https',
      'x-forwarded-host': request.headers.host,
    },
  }, upstreamResponse => {
    response.writeHead(upstreamResponse.statusCode ?? 502, upstreamResponse.headers);
    upstreamResponse.pipe(response);
  });
  upstream.on('error', error => {
    response.writeHead(502, {'content-type': 'text/plain; charset=utf-8'});
    response.end(error.message);
  });
  request.pipe(upstream);
});

server.listen(Number(listenPort), '127.0.0.1', () => {
  process.stdout.write(`HTTPS proxy listening on https://127.0.0.1:${listenPort}\n`);
});
