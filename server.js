const http = require('http');
const fs = require('fs');
const path = require('path');
const os = require('os');
const { exec } = require('child_process');

const PORT = process.env.PORT || 3000;
const ROOT_DIR = __dirname;

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.webp': 'image/webp',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.php': 'text/plain; charset=utf-8'
};

const server = http.createServer((req, res) => {
  const urlParts = req.url.split('?');
  const urlPath = decodeURIComponent(urlParts[0]);

  // Set CORS headers
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }

  // ==========================================
  // MOCK API ENDPOINTS FOR LOCAL DEV IN VS CODE
  // ==========================================
  if (urlPath === '/api/server-info' || urlPath === '/api/server-info.php') {
    const interfaces = os.networkInterfaces();
    let localIp = '127.0.0.1';
    for (const name of Object.keys(interfaces)) {
      for (const net of interfaces[name]) {
        if (net.family === 'IPv4' && !net.internal) {
          localIp = net.address;
          break;
        }
      }
    }

    const info = {
      status: 'success',
      timestamp: new Date().toLocaleString('vi-VN'),
      project: 'Đề tài 10 - Triển khai hệ thống Hosting E-commerce Đa nền tảng',
      node: {
        name: 'Node: Local Dev Server (VS Code)',
        color: '#10b981',
        serverSoftware: 'Node.js ' + process.version + ' (Local Environment)',
        os: os.type() + ' ' + os.release() + ' (' + os.platform() + ')',
        hostname: os.hostname(),
        serverIp: localIp,
        serverPort: PORT
      },
      database: {
        connected: false,
        mode: 'Local File JSON (Dev Mode)',
        targetHost: '100.72.145.103:3306',
        database: 'shop_db',
        message: 'Đang chạy Local Dev Server (VS Code) với CSDL JSON file-based'
      },
      client: {
        ip: req.socket.remoteAddress || '127.0.0.1',
        userAgent: req.headers['user-agent'] || 'Browser'
      }
    };

    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify(info, null, 2));
    return;
  }

  // Orders API
  if (urlPath === '/api/orders' || urlPath === '/api/orders.php') {
    const ordersFile = path.join(ROOT_DIR, 'api', 'data', 'orders.json');
    if (req.method === 'GET') {
      const data = fs.existsSync(ordersFile) ? fs.readFileSync(ordersFile, 'utf8') : '[]';
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(data);
      return;
    } else if (req.method === 'POST') {
      let body = '';
      req.on('data', chunk => { body += chunk; });
      req.on('end', () => {
        try {
          const input = JSON.parse(body || '{}');
          let orders = [];
          if (fs.existsSync(ordersFile)) {
            orders = JSON.parse(fs.readFileSync(ordersFile, 'utf8') || '[]');
          }
          const newOrder = {
            id: 'DH' + Date.now().toString().slice(-6),
            customer: input.customer || 'Khách vãng lai',
            phone: input.phone || '',
            address: input.address || '',
            date: new Date().toLocaleDateString('vi-VN'),
            total: input.total || 0,
            status: 'Mới đặt',
            pay: input.pay || 'Tiền mặt',
            items: input.items || [],
            processedBy: 'Local Dev Server (VS Code)'
          };
          orders.unshift(newOrder);
          fs.writeFileSync(ordersFile, JSON.stringify(orders, null, 2), 'utf8');
          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'success', order: newOrder }));
        } catch (e) {
          res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'error', message: e.message }));
        }
      });
      return;
    }
  }

  // Products API
  if (urlPath === '/api/products' || urlPath === '/api/products.php') {
    const productsFile = path.join(ROOT_DIR, 'api', 'data', 'products.json');
    if (req.method === 'GET') {
      const data = fs.existsSync(productsFile) ? fs.readFileSync(productsFile, 'utf8') : '[]';
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(data);
      return;
    }
  }

  // ==========================================
  // STATIC FILES SERVING & SPA FALLBACK
  // ==========================================
  let safePath = path.normalize(urlPath).replace(/^(\.\.[\/\\])+/, '');
  if (safePath === '/' || safePath === '\\') {
    safePath = '/index.html';
  }

  let filePath = path.join(ROOT_DIR, safePath);

  fs.stat(filePath, (err, stats) => {
    if (!err && stats.isFile()) {
      const ext = path.extname(filePath).toLowerCase();
      const contentType = MIME_TYPES[ext] || 'application/octet-stream';
      res.writeHead(200, { 'Content-Type': contentType });
      fs.createReadStream(filePath).pipe(res);
    } else {
      const indexPath = path.join(ROOT_DIR, 'index.html');
      fs.stat(indexPath, (err2, stats2) => {
        if (!err2 && stats2.isFile()) {
          res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
          fs.createReadStream(indexPath).pipe(res);
        } else {
          res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
          res.end('404 Not Found');
        }
      });
    }
  });
});

let currentPort = PORT;

function startServer(port) {
  server.listen(port, () => {
    const url = `http://localhost:${port}`;
    console.log('='.repeat(55));
    console.log(`  🛒 TECHNO STORE - LOCAL DEV SERVER`);
    console.log(`  🔗 Địa chỉ: ${url}`);
    console.log(`  📁 Thư mục: ${ROOT_DIR}`);
    console.log(`  🔌 API Endpoint: ${url}/api/server-info`);
    console.log(`  💡 Mở trình duyệt để xem giao diện web`);
    console.log(`  🛑 Nhấn Ctrl + C để dừng server`);
    console.log('='.repeat(55));

    if (process.platform === 'win32' && !process.env.NO_AUTO_OPEN) {
      exec(`start ${url}`);
    }
  });
}

server.on('error', (err) => {
  if (err.code === 'EADDRINUSE') {
    console.log(`⚠️ Cổng ${currentPort} đang bận, đang tự động chuyển sang cổng ${currentPort + 1}...`);
    currentPort++;
    startServer(currentPort);
  } else {
    console.error('Lỗi khởi động server:', err);
  }
});

startServer(currentPort);

