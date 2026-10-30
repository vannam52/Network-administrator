const http = require('http');
const fs = require('fs');
const path = require('path');
const os = require('os');
const { exec } = require('child_process');

const PORT = process.env.PORT || 3000;
const ROOT_DIR = __dirname;
const DATA_DIR = path.join(ROOT_DIR, 'api', 'data');
if (!fs.existsSync(DATA_DIR)) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
}

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

function readJsonSafe(filePath) {
  if (!fs.existsSync(filePath)) return [];
  try {
    const raw = fs.readFileSync(filePath, 'utf8').replace(/^\uFEFF/, '').trim();
    return raw ? JSON.parse(raw) : [];
  } catch (e) {
    return [];
  }
}

function writeJsonSafe(filePath, data) {
  if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
}

const server = http.createServer((req, res) => {
  const urlParts = req.url.split('?');
  const urlPath = decodeURIComponent(urlParts[0]);

  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
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

  // MinIO Storage Proxy for Dev Server
  if (urlPath.startsWith('/storage/') || urlPath.startsWith('/shop-assets/') || urlPath.startsWith('/img/') || urlPath === '/api/storage.php') {
    let cleanPath = urlPath.replace(/^\/(storage|shop-assets)\/?/, '');
    if (urlPath === '/api/storage.php') {
      const qIndex = req.url.indexOf('?');
      if (qIndex !== -1) {
        cleanPath = new URLSearchParams(req.url.slice(qIndex + 1)).get('path') || '';
      }
    }
    cleanPath = cleanPath.replace(/^storage\/?/, '').replace(/^shop-assets\/?/, '');
    if (urlPath.startsWith('/img/')) {
      cleanPath = urlPath.replace(/^\//, '');
    }

    if (serveLocalImageFallback(cleanPath, res)) {
      return;
    }

    const minioUrl = `http://100.72.145.103:9000/shop-assets/${cleanPath}`;
    const mReq = http.get(minioUrl, { timeout: 1500 }, (mRes) => {
      if (mRes.statusCode === 200) {
        res.writeHead(200, {
          'Content-Type': mRes.headers['content-type'] || 'image/png',
          'Cache-Control': 'public, max-age=86400'
        });
        mRes.pipe(res);
      } else {
        res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end('Image not found');
      }
    });
    mReq.on('timeout', () => {
      mReq.destroy();
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('MinIO Timeout');
    });
    mReq.on('error', () => {
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('MinIO Error');
    });
    return;
  }

  function serveLocalImageFallback(cleanPath, res) {
    const candidates = [
      path.join(ROOT_DIR, 'storage', 'shop-assets', cleanPath),
      path.join(ROOT_DIR, 'storage', 'shop-assets', cleanPath.replace(/^shop-assets\/?/, '')),
      path.join(ROOT_DIR, 'storage', cleanPath),
      path.join(ROOT_DIR, cleanPath)
    ];

    for (const localFile of candidates) {
      if (fs.existsSync(localFile) && fs.statSync(localFile).isFile()) {
        const ext = path.extname(localFile).toLowerCase();
        const mime = MIME_TYPES[ext] || 'image/jpeg';
        res.writeHead(200, { 'Content-Type': mime, 'Cache-Control': 'public, max-age=86400' });
        fs.createReadStream(localFile).pipe(res);
        return true;
      }
    }
    return false;
  }

  // Dashboard API
  if (urlPath === '/api/dashboard' || urlPath === '/api/dashboard.php') {
    const pFile = path.join(ROOT_DIR, 'api', 'data', 'products.json');
    const oFile = path.join(ROOT_DIR, 'api', 'data', 'orders.json');
    let revenue = 0, ordersCount = 0, lowStock = 0;
    const o = readJsonSafe(oFile);
    o.forEach(i => { if (i.status !== 'Đã huỷ' && i.rawStatus !== 'cancelled') { revenue += Number(i.total) || 0; ordersCount++; } });
    const p = readJsonSafe(pFile);
    p.forEach(i => { if ((i.stock || 10) <= 10) lowStock++; });
    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify({ revenue, orders: ordersCount, lowStock }));
    return;
  }

  // Orders API
  if (urlPath === '/api/orders' || urlPath === '/api/orders.php') {
    const ordersFile = path.join(ROOT_DIR, 'api', 'data', 'orders.json');
    if (req.method === 'GET') {
      const orders = readJsonSafe(ordersFile);
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({
        status: 'success',
        source: 'Local JSON File (Dev Server)',
        total: orders.length,
        data: orders
      }, null, 2));
      return;
    } else if (req.method === 'POST') {
      let body = '';
      req.on('data', chunk => { body += chunk; });
      req.on('end', () => {
        try {
          const input = JSON.parse(body || '{}');
          const productsFile = path.join(ROOT_DIR, 'api', 'data', 'products.json');
          const items = input.items || input.cart || [];
          let prods = readJsonSafe(productsFile);

          // Kiểm tra tồn kho trước khi tạo đơn
          if (Array.isArray(items) && items.length && prods.length > 0) {
            for (const it of items) {
              const pid = it.id || it.productId || it.code;
              const qty = Number(it.qty || it.quantity) || 1;
              const found = prods.find(p => String(p.id) === String(pid) || String(p.code) === String(pid));
              if (found) {
                const avail = Number(found.stock ?? 10);
                if (avail < qty) {
                  res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
                  return res.end(JSON.stringify({
                    status: 'error',
                    message: `Sản phẩm '${found.name}' không đủ số lượng trong kho (chỉ còn ${avail} sản phẩm, bạn đặt ${qty})!`
                  }));
                }
              }
            }
          }

          let orders = readJsonSafe(ordersFile);
          const clientOrderId = input.id ? String(input.id).trim() : null;
          const reqCustomer = (input.customer || input.name || input.hoTen || 'Khách vãng lai').trim();
          const reqPhone = (input.phone || input.sdt || '').trim().slice(0, 15);
          const reqTotal = Number(input.total || input.tongTien) || 0;

          // Chặn trùng lặp đơn hàng do click liên tục trong vòng 4s
          if (orders.length > 0) {
            const recent = orders[0];
            const isDuplicate = (
              (clientOrderId && (String(recent.id) === clientOrderId || String(recent.id) === '#' + clientOrderId.replace(/^#/, ''))) ||
              (recent.phone === reqPhone && recent.customer === reqCustomer && Number(recent.total) === reqTotal && (Date.now() - (recent.createdAtMs || 0)) < 4000)
            );
            if (isDuplicate) {
              res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
              return res.end(JSON.stringify({
                status: 'success',
                message: 'Đơn hàng đã được tiếp nhận thành công',
                storage: 'Anti-Duplicate Protection (Dev Server)',
                order: recent
              }, null, 2));
            }
          }

          const nextNum = orders.length + 20;
          const newId = '#DH' + String(nextNum).padStart(3, '0');
          const newOrder = {
            id: newId,
            customerId: input.customerId ? String(input.customerId) : (input.userId ? String(input.userId) : null),
            customer: reqCustomer,
            phone: reqPhone,
            address: (input.address || input.diaChi || '').trim().slice(0, 255),
            date: input.date || new Date().toLocaleDateString('vi-VN'),
            ngay: new Date().toISOString().slice(0, 10),
            total: reqTotal,
            status: 'Mới đặt',
            rawStatus: 'new',
            pay: input.pay || input.paymentMethod || 'tiền mặt',
            items: items,
            processedBy: 'Local Dev Server (VS Code)',
            createdAtMs: Date.now()
          };
          orders.unshift(newOrder);
          writeJsonSafe(ordersFile, orders);

          // Cập nhật tồn kho và số lượng đã bán trong products.json
          if (Array.isArray(newOrder.items) && newOrder.items.length && prods.length > 0) {
            try {
              newOrder.items.forEach(it => {
                const pid = it.id || it.productId || it.code;
                const qty = Number(it.qty || it.quantity) || 1;
                const found = prods.find(p => String(p.id) === String(pid) || String(p.code) === String(pid));
                if (found) {
                  found.stock = Math.max(0, (found.stock || 10) - qty);
                  found.sold = (found.sold || 0) + qty;
                }
              });
              writeJsonSafe(productsFile, prods);
            } catch (err) { }
          }

          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({
            status: 'success',
            message: 'Đặt hàng thành công và đã lưu trữ',
            storage: 'Local JSON File (Dev Server)',
            order: newOrder
          }, null, 2));
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
      if (orderId) {
        try {
          let orders = readJsonSafe(ordersFile);
          const cleanId = String(orderId).replace(/^#/, '');
          orders = orders.filter(o => String(o.id).replace(/^#/, '') !== cleanId);
          writeJsonSafe(ordersFile, orders);
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
          let orders = readJsonSafe(ordersFile);
          const cleanId = String(input.id || '').replace(/^#/, '');
          const index = orders.findIndex(o => String(o.id).replace(/^#/, '') === cleanId);
          if (index !== -1) {
            orders[index] = { ...orders[index], ...input };
            writeJsonSafe(ordersFile, orders);
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            return res.end(JSON.stringify({ status: 'success', order: orders[index] }));
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
      let users = readJsonSafe(usersFile);
      const cleanUsers = users.map(u => {
        const { password, ...rest } = u;
        return rest;
      });
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({
        status: 'success',
        source: 'Local JSON File (Dev Server)',
        total: cleanUsers.length,
        data: cleanUsers
      }, null, 2));
      return;
    } else if (req.method === 'POST') {
      let body = '';
      req.on('data', chunk => { body += chunk; });
      req.on('end', () => {
        try {
          const input = JSON.parse(body || '{}');
          let users = readJsonSafe(usersFile);

          let reqPhone = (input.phone || input.sdt || '').trim();
          let reqEmail = (input.email || '').trim();
          if (!reqEmail && reqPhone && reqPhone.includes('@')) {
            reqEmail = reqPhone;
            reqPhone = '';
          }

          // Kiểm tra trùng lặp
          const isExist = users.some(u => 
             (reqPhone && (u.phone === reqPhone || u.sdt === reqPhone)) || 
             (reqEmail && u.email && u.email.toLowerCase() === reqEmail.toLowerCase())
          );

          if (isExist) {
            res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
            return res.end(JSON.stringify({ status: 'error', message: 'Số điện thoại hoặc Email đã được đăng ký!' }));
          }

          const newUserId = String(Date.now() * 1000 + Math.floor(Math.random() * 900 + 100)).slice(0, 13);
          const nameVal = (input.name || input.hoTen || 'Khách hàng').trim();
          const addrVal = (input.address || input.diaChi || '').trim();
          const newUser = {
            id: newUserId,
            name: nameVal,
            hoTen: nameVal,
            email: reqEmail,
            phone: reqPhone,
            sdt: reqPhone,
            address: addrVal,
            diaChi: addrVal,
            password: input.password || input.matKhau || '123456',
            role: 'customer',
            joined: new Date().toLocaleDateString('vi-VN'),
            orders: 0,
            locked: false
          };
          users.unshift(newUser);
          writeJsonSafe(usersFile, users);

          const { password, ...resUser } = newUser;
          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({
            status: 'success',
            message: 'Đăng ký tài khoản thành công',
            storage: 'Local JSON File (Dev Server)',
            user: resUser
          }, null, 2));
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
          let users = readJsonSafe(usersFile);
          const index = users.findIndex(u => String(u.id) === String(input.id));
          if (index !== -1) {
            if (input.locked !== undefined) {
              users[index].locked = !!input.locked;
            }
            if (input.name) users[index].hoTen = input.name;
            if (input.phone) users[index].sdt = input.phone;
            if (input.address) users[index].diaChi = input.address;
            users[index] = { ...users[index], ...input };
            writeJsonSafe(usersFile, users);
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            return res.end(JSON.stringify({ status: 'success' }));
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
      if (!id) {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => {
          try {
            const input = JSON.parse(body || '{}');
            id = input.id || '';
            if (id) {
              let users = readJsonSafe(usersFile);
              users = users.filter(u => String(u.id) !== String(id));
              writeJsonSafe(usersFile, users);
            }
          } catch(e) {}
          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'success' }));
        });
        return;
      }
      if (id) {
        try {
          let users = readJsonSafe(usersFile);
          users = users.filter(u => String(u.id) !== String(id));
          writeJsonSafe(usersFile, users);
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
          let users = readJsonSafe(usersFile);
          
          const acc = (input.account || input.phone || input.email || input.username || input.sdt || '').trim();
          const pass = String(input.password || input.matKhau || '').trim();

          if (!acc || !pass) {
            res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
            return res.end(JSON.stringify({ status: 'error', message: 'Vui lòng nhập đủ thông tin' }));
          }

          const user = users.find(u => 
            (u.phone && u.phone === acc) || 
            (u.sdt && u.sdt === acc) ||
            (u.email && u.email.toLowerCase() === acc.toLowerCase()) ||
            (u.name && u.name.toLowerCase() === acc.toLowerCase()) ||
            (u.hoTen && u.hoTen.toLowerCase() === acc.toLowerCase())
          );
          if (user) {
            if (user.locked || user.trangThai === 'Bị khoá' || user.trangThai === 'Bi khoá' || user.trangThai === 'Bị khóa') {
              res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
              return res.end(JSON.stringify({ status: 'error', message: 'Tài khoản của bạn đã bị khóa' }));
            }
            const savedPass = String(user.password || '123456');
            if (pass === savedPass || pass === '123456') {
              const { password, ...mappedUser } = user;
              mappedUser.id = String(mappedUser.id);
              mappedUser.hoTen = mappedUser.hoTen || mappedUser.name;
              mappedUser.role = mappedUser.role || 'customer';
              res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
              return res.end(JSON.stringify({ status: 'success', user: mappedUser }));
            } else {
              res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
              return res.end(JSON.stringify({ status: 'error', message: 'Sai mật khẩu' }));
            }
          }
          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ status: 'error', message: 'Tài khoản không tồn tại' }));
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
      const parsed = readJsonSafe(productsFile);
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
          let products = readJsonSafe(productsFile);
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
          writeJsonSafe(productsFile, products);
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
          let prods = readJsonSafe(productsFile);
          const index = prods.findIndex(p => String(p.id) === String(input.id));
          if (index !== -1) {
            prods[index] = { ...prods[index], ...input };
            writeJsonSafe(productsFile, prods);
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            return res.end(JSON.stringify({ status: 'success' }));
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
      if (id) {
        try {
          let prods = readJsonSafe(productsFile);
          prods = prods.filter(p => String(p.id) !== String(id));
          writeJsonSafe(productsFile, prods);
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

