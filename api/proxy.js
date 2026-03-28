const https = require('https');

/**
 * Vercel serverless proxy: forwards /pbxapi/* to mt.voipat.com/pbxapi/*
 * Eliminates CORS issues — the browser calls same-origin www.voipat.com,
 * this function forwards server-side.
 */
module.exports = async function handler(req, res) {
  // CORS headers (allow the browser request to reach this function)
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key');

  if (req.method === 'OPTIONS') {
    res.status(204).end();
    return;
  }

  // Build target path: /pbxapi/<path>?<remaining query params>
  const { path: pathParam, ...rest } = req.query;
  const subPath = Array.isArray(pathParam) ? pathParam.join('/') : (pathParam || '');
  const qs = new URLSearchParams(rest).toString();
  const targetPath = `/pbxapi/${subPath}${qs ? '?' + qs : ''}`;

  // Re-serialize body (Vercel auto-parses JSON)
  let body = '';
  if (req.body && req.method !== 'GET' && req.method !== 'HEAD') {
    body = typeof req.body === 'string' ? req.body : JSON.stringify(req.body);
  }

  const options = {
    hostname: 'mt.voipat.com',
    port: 443,
    path: targetPath,
    method: req.method,
    headers: { 'Content-Type': 'application/json' },
  };

  // Forward auth headers
  if (req.headers['authorization']) options.headers['Authorization'] = req.headers['authorization'];
  if (req.headers['x-api-key'])     options.headers['X-API-Key']     = req.headers['x-api-key'];
  if (body)                          options.headers['Content-Length'] = Buffer.byteLength(body);

  return new Promise((resolve) => {
    const proxyReq = https.request(options, (proxyRes) => {
      let data = '';
      proxyRes.on('data', (chunk) => { data += chunk; });
      proxyRes.on('end', () => {
        res.status(proxyRes.statusCode).end(data);
        resolve();
      });
    });

    proxyReq.on('error', (err) => {
      res.status(502).json({ error: 'Proxy error: ' + err.message });
      resolve();
    });

    if (body) proxyReq.write(body);
    proxyReq.end();
  });
};
