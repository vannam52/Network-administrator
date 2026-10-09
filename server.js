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

  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }

  // Mock API endpoints
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

  // Dashboard API
  if (urlPath === '/api/dashboard' || urlPath === '/api/dashboard.php') {
    const pFile = path.join(ROOT_DIR, 'api', 'data', 'products.json');
    const oFile = path.join(ROOT_DIR, 'api', 'data', 'orders.json');
    let revenue = 0, ordersCount = 0, lowStock = 0;
    if (fs.existsSync(oFile)) {
      const o = JSON.parse(fs.readFileSync(oFile, 'utf8') || '[]');
      o.forEach(i => { if (i.status !== 'Đã huỷ') { revenue += Number(i.total) || 0; ordersCount++; } });
    }
    if (fs.existsSync(pFile)) {
      const p = JSON.parse(fs.readFileSync(pFile, 'utf8') || '[]');
      p.forEach(i => { if ((i.stock || 10) <= 10) lowStock++; });
    }
    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify({ revenue, orders: ordersCount, lowStock }));
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
            id: input.id || ('DH' + Date.now().toString().slice(-6)),
            customer: input.customer || 'Khách vãng lai',
            phone: input.phone || '',
            address: input.address || '',
            date: input.date || new Date().toLocaleDateString('vi-VN'),
            total: Number(input.total) || 0,
            status: input.status || 'Mới đặt',
            pay: input.pay || 'Tiền mặt',
            items: input.items || [],
            processedBy: 'Local Dev Server (VS Code)'
          };
          orders.unshift(newOrder);
          fs.writeFileSync(ordersFile, JSON.stringify(orders, null, 2), 'utf8');

          // Cập nhật tồn kho và số lượng đã bán trong products.json
          const productsFile = path.join(ROOT_DIR, 'api', 'data', 'products.json');
          if (Array.isArray(input.items) && input.items.length && fs.existsSync(productsFile)) {
            try {
              let prods = JSON.parse(fs.readFileSync(productsFile, 'utf8') || '[]');
              input.items.forEach(it => {
                const pid = it.id || it.product_id;
                const qty = Number(it.qty || it.quantity) || 1;
                const found = prods.find(p => String(p.id) === String(pid) || String(p.code) === String(pid));
                if (found) {
                  found.stock = Math.max(0, (found.stock || 10) - qty);
                  found.sold = (found.sold || 0) + qty;
                }
              });
              fs.writeFileSync(productsFile, JSON.stringify(prods, null, 2), 'utf8');
            } catch (err) { }
          }

          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'success', order: newOrder }));
        } catch (e) {
          res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'error', message: e.message }));
        }
      });
      return;
    } else if (req.method === 'DELETE') {
      const qIndex = req.url.indexOf('?');
      let orderId = '';
      if (qIndex !== -1) {
        const params = new URLSearchParams(req.url.slice(qIndex + 1));
        orderId = params.get('id') || '';
      }
      if (orderId && fs.existsSync(ordersFile)) {
        try {
          let orders = JSON.parse(fs.readFileSync(ordersFile, 'utf8') || '[]');
          orders = orders.filter(o => o.id !== orderId && o.id !== ('#' + orderId));
          fs.writeFileSync(ordersFile, JSON.stringify(orders, null, 2), 'utf8');
        } catch (e) { }
      }
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({ status: 'success', message: 'Đã xóa đơn hàng' }));
      return;
    } else if (req.method === 'PUT') {
      let body = '';
      req.on('data', chunk => body += chunk);
      req.on('end', () => {
        try {
          const input = JSON.parse(body || '{}');
          if (fs.existsSync(ordersFile)) {
            let orders = JSON.parse(fs.readFileSync(ordersFile, 'utf8') || '[]');
            const index = orders.findIndex(o => String(o.id) === String(input.id));
            if (index !== -1) {
              orders[index] = { ...orders[index], ...input };
              fs.writeFileSync(ordersFile, JSON.stringify(orders, null, 2), 'utf8');
              res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
              return res.end(JSON.stringify({ status: 'success', order: orders[index] }));
            }
          }
          throw new Error('Không tìm thấy đơn hàng');
        } catch (e) {
          res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'error', message: e.message }));
        }
      });
      return;
    }
  }

  // Users API
  if (urlPath === '/api/users' || urlPath === '/api/users.php') {
    const usersFile = path.join(ROOT_DIR, 'api', 'data', 'users.json');
    if (req.method === 'GET') {
      const data = fs.existsSync(usersFile) ? fs.readFileSync(usersFile, 'utf8') : '[]';
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(data);
      return;
    } else if (req.method === 'POST') {
      let body = '';
      req.on('data', chunk => { body += chunk; });
      req.on('end', () => {
        try {
          const input = JSON.parse(body || '{}');
          let users = [];
          if (fs.existsSync(usersFile)) {
            users = JSON.parse(fs.readFileSync(usersFile, 'utf8') || '[]');
          }
          const newId = 'KH' + String(users.length + 1).padStart(3, '0');
          const newUser = {
            id: newId,
            name: (input.name || 'Khách hàng').trim(),
            email: (input.email || '').trim(),
            phone: (input.phone || '').trim(),
            password: input.password || '123456', // Lưu tạm pass dạng plain text để test local
            joined: new Date().toLocaleDateString('vi-VN'),
            orders: 0,
            locked: false
          };
          users.unshift(newUser);
          fs.writeFileSync(usersFile, JSON.stringify(users, null, 2), 'utf8');
          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'success', user: newUser }));
        } catch (e) {
          res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'error', message: e.message }));
        }
      });
      return;
    } else if (req.method === 'PUT') {
      let body = '';
      req.on('data', chunk => body += chunk);
      req.on('end', () => {
        try {
          const input = JSON.parse(body || '{}');
          if (fs.existsSync(usersFile)) {
            let users = JSON.parse(fs.readFileSync(usersFile, 'utf8') || '[]');
            const index = users.findIndex(u => String(u.id) === String(input.id));
            if (index !== -1) {
              users[index] = { ...users[index], ...input };
              fs.writeFileSync(usersFile, JSON.stringify(users, null, 2), 'utf8');
              res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
              return res.end(JSON.stringify({ status: 'success', user: users[index] }));
            }
          }
          throw new Error('Không tìm thấy KH');
        } catch (e) {
          res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'error', message: e.message }));
        }
      });
      return;
    } else if (req.method === 'DELETE') {
      const qIndex = req.url.indexOf('?');
      let id = qIndex !== -1 ? new URLSearchParams(req.url.slice(qIndex + 1)).get('id') : '';
      if (id && fs.existsSync(usersFile)) {
        try {
          let users = JSON.parse(fs.readFileSync(usersFile, 'utf8') || '[]');
          users = users.filter(u => String(u.id) !== String(id));
          fs.writeFileSync(usersFile, JSON.stringify(users, null, 2), 'utf8');
        } catch (e) { }
      }
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({ status: 'success' }));
      return;
    }
  }

  // Auth API
  if (urlPath === '/api/auth' || urlPath === '/api/auth.php') {
    if (req.method === 'POST') {
      let body = '';
      req.on('data', chunk => body += chunk);
      req.on('end', () => {
        try {
          const input = JSON.parse(body || '{}');
          const usersFile = path.join(ROOT_DIR, 'api', 'data', 'users.json');
          let users = fs.existsSync(usersFile) ? JSON.parse(fs.readFileSync(usersFile, 'utf8') || '[]') : [];
          
          const user = users.find(u => u.phone === input.phone || u.email === input.phone);
          if (user) {
            const savedPass = user.password || '123456';
            if (input.password === savedPass) {
              res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
              return res.end(JSON.stringify({ status: 'success', user: user }));
            }
          }
          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'error', message: 'Sai mật khẩu' }));
        } catch (e) {
          res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'error' }));
        }
      });
      return;
    }
  }

  // Products API
  if (urlPath === '/api/products' || urlPath === '/api/products.php') {
    const productsFile = path.join(ROOT_DIR, 'api', 'data', 'products.json');
    if (req.method === 'GET') {
      let raw = '[]';
      if (fs.existsSync(productsFile)) {
        raw = fs.readFileSync(productsFile, 'utf8');
      }
      let parsed = [];
      try { parsed = JSON.parse(raw); } catch (e) { parsed = []; }
      const responsePayload = {
        status: 'success',
        source: 'Local JSON File (Dev Server)',
        total: parsed.length,
        data: parsed
      };
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify(responsePayload, null, 2));
      return;
    } else if (req.method === 'POST') {
      let body = '';
      req.on('data', chunk => body += chunk);
      req.on('end', () => {
        try {
          const input = JSON.parse(body || '{}');
          let products = [];
          if (fs.existsSync(productsFile)) {
            products = JSON.parse(fs.readFileSync(productsFile, 'utf8') || '[]');
          }
          const newId = products.length > 0 ? Math.max(...products.map(p => p.id || 0)) + 1 : 1;
          const newProduct = {
            id: newId,
            code: input.code || ('SP0' + String(newId).padStart(2, '0')),
            name: input.name || 'Sản phẩm mới',
            price: Number(input.price) || 0,
            stock: Number(input.stock) || 10,
            category: input.category || 'Điện thoại',
            brand: 'Khác',
            isNew: true,
            imported: Number(input.stock) || 10,
            sold: 0
          };
          products.push(newProduct);
          fs.writeFileSync(productsFile, JSON.stringify(products, null, 2), 'utf8');
          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'success' }));
        } catch (e) {
          res.writeHead(400);
          res.end('Error');
        }
      });
      return;
    } else if (req.method === 'PUT') {
      let body = '';
      req.on('data', chunk => body += chunk);
      req.on('end', () => {
        try {
          const input = JSON.parse(body || '{}');
          if (fs.existsSync(productsFile)) {
            let prods = JSON.parse(fs.readFileSync(productsFile, 'utf8') || '[]');
            const index = prods.findIndex(p => String(p.id) === String(input.id));
            if (index !== -1) {
              prods[index] = { ...prods[index], ...input };
              fs.writeFileSync(productsFile, JSON.stringify(prods, null, 2), 'utf8');
              res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
              return res.end(JSON.stringify({ status: 'success' }));
            }
          }
          throw new Error('Không tìm thấy SP');
        } catch (e) {
          res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'error', message: e.message }));
        }
      });
      return;
    } else if (req.method === 'DELETE') {
      const qIndex = req.url.indexOf('?');
      let id = qIndex !== -1 ? new URLSearchParams(req.url.slice(qIndex + 1)).get('id') : '';
      if (id && fs.existsSync(productsFile)) {
        try {
          let prods = JSON.parse(fs.readFileSync(productsFile, 'utf8') || '[]');
          prods = prods.filter(p => String(p.id) !== String(id));
          fs.writeFileSync(productsFile, JSON.stringify(prods, null, 2), 'utf8');
        } catch (e) { }
      }
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({ status: 'success' }));
      return;
    }
  }

  // SPA route /admin -> root index.html
  if (urlPath === '/admin' || urlPath === '/admin/' || urlPath.startsWith('/admin?')) {
    const indexPath = path.join(ROOT_DIR, 'index.html');
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    fs.createReadStream(indexPath).pipe(res);
    return;
  }

  // Static files and SPA fallback
  let safePath = path.normalize(urlPath).replace(/^(\.\.[\/\\])+/, '');
  if (safePath === '/' || safePath === '\\') {
    safePath = '/index.html';
  }

  let filePath = path.join(ROOT_DIR, safePath);

  fs.stat(filePath, (err, stats) => {
    if (!err && stats.isDirectory()) {
      filePath = path.join(filePath, 'index.html');
      try {
        stats = fs.statSync(filePath);
      } catch (e) {
        err = e;
      }
    }

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
    console.log(`  🛒 PHONE STORE - LOCAL DEV SERVER`);
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

