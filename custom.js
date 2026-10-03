(function () {
  const config = window.PROJECT_CONFIG || {};

  // 1. Cập nhật tiêu đề trang
  if (config.siteTitle) {
    document.title = config.siteTitle;
  }

  let serverData = null;

  // 2. Hàm gọi API lấy thông tin Server Node thực tế (IIS hay Apache)
  async function fetchServerInfo() {
    try {
      // Thử gọi server-info.php trước (khi chạy trên Apache/IIS), nếu không thì gọi /api/server-info (local)
      let res = await fetch('/api/server-info.php').catch(() => null);
      if (!res || !res.ok) {
        res = await fetch('/api/server-info');
      }
      if (res && res.ok) {
        serverData = await res.json();
        updateBadgeWithServerData(serverData);
      }
    } catch (e) {
      console.log('Chưa kết nối được API server-info:', e.message);
    }
  }

  // 3. Cập nhật bảng thông tin Đề tài 10 với dữ liệu thời gian thực từ Backend
  function updateBadgeWithServerData(data) {
    const nodeEl = document.getElementById('badge-node-name');
    const dotEl = document.getElementById('badge-node-dot');
    const metaEl = document.getElementById('badge-node-meta');

    if (data && data.node) {
      if (nodeEl) nodeEl.textContent = data.node.name;
      if (dotEl) {
        dotEl.style.background = data.node.color || '#10b981';
        dotEl.style.boxShadow = `0 0 8px ${data.node.color || '#10b981'}`;
      }
      if (metaEl) {
        const dbInfo = data.database ? `<div style="margin-top:2px;">CSDL: <span style="color:${data.database.connected ? '#10b981' : '#f59e0b'}; font-weight:600;">${data.database.mode}</span></div>` : '';
        metaEl.innerHTML = `
          <div>Máy chủ: <b>${data.node.serverSoftware}</b></div>
          <div>HĐH: ${data.node.os}</div>
          <div>IP / Host: <code>${data.node.serverIp} (${data.node.hostname})</code></div>
          ${dbInfo}
          <div style="color: #64748b; font-size: 10px; margin-top: 2px;">Cập nhật: ${data.timestamp}</div>
        `;
      }
    }
  }

  // 4. Tạo giao diện Badge Đề tài 10
  function initProjectBadge() {
    if (!config.showProjectBadge) return;
    const info = config.projectInfo || {};

    const badge = document.createElement('div');
    badge.id = 'project-badge';
    badge.style.position = 'fixed';
    badge.style.top = '12px';
    badge.style.right = '12px';
    badge.style.zIndex = '9999';
    badge.style.maxWidth = '360px';
    badge.style.fontFamily = 'system-ui, -apple-system, sans-serif';
    badge.style.fontSize = '12px';
    badge.style.transition = 'all 0.3s ease';

    badge.innerHTML = `
      <div id="badge-content" style="
        background: rgba(15, 23, 42, 0.94);
        color: #f8fafc;
        border: 1px solid rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(12px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.35);
        border-radius: 14px;
        padding: 10px 14px;
        line-height: 1.4;
      ">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px;">
          <span style="font-weight: 700; color: #38bdf8; font-size: 13px;">${info.topic || 'ĐỀ TÀI 10'}</span>
          <div style="display: flex; gap: 6px; align-items: center;">
            <button id="badge-refresh-btn" style="
              background: rgba(255,255,255,0.1); border: none; color: #cbd5e1; cursor: pointer; border-radius: 4px; padding: 2px 6px; font-size: 10px;
            " title="Kiểm tra lại Server Backend">🔄 Kiểm tra</button>
            <button id="badge-toggle-btn" style="
              background: none; border: none; color: #94a3b8; cursor: pointer; padding: 2px 4px; font-size: 11px;
            " title="Thu gọn/mở rộng">▼</button>
          </div>
        </div>
        <div id="badge-details" style="display: flex; flex-direction: column; gap: 5px;">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span id="badge-node-dot" style="
              display: inline-block; width: 9px; height: 9px; border-radius: 50%;
              background: #10b981; box-shadow: 0 0 8px #10b981;
            "></span>
            <span id="badge-node-name" style="font-weight: 600; color: #e2e8f0;">${info.currentNode || 'Đang nhận diện Server...'}</span>
          </div>
          <div id="badge-node-meta" style="color: #cbd5e1; font-size: 11px;">
            <div>Môn: <b>${info.subject || 'Quản trị mạng'}</b></div>
          </div>
          ${info.members && info.members.length ? `
            <div style="color: #94a3b8; font-size: 11px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 4px; margin-top: 2px;">
              ${info.members.map(m => `<div>• ${m.name} (${m.id})</div>`).join('')}
            </div>
          ` : ''}
        </div>
      </div>
    `;

    document.body.appendChild(badge);

    const toggleBtn = document.getElementById('badge-toggle-btn');
    const refreshBtn = document.getElementById('badge-refresh-btn');
    const details = document.getElementById('badge-details');
    let isCollapsed = false;

    if (toggleBtn && details) {
      toggleBtn.addEventListener('click', () => {
        isCollapsed = !isCollapsed;
        details.style.display = isCollapsed ? 'none' : 'flex';
        toggleBtn.textContent = isCollapsed ? '▲' : '▼';
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        refreshBtn.textContent = '⏳...';
        fetchServerInfo().then(() => {
          setTimeout(() => { refreshBtn.textContent = '🔄 Kiểm tra'; }, 500);
        });
      });
    }
  }

  // 5. Khắc phục sự kiện cho Menu Header (Điện thoại, Máy tính bảng, Phụ kiện)
  function initHeaderNavigation() {
    document.addEventListener('click', (e) => {
      const target = e.target.closest('header nav button');
      if (!target) return;

      const categoryName = target.textContent.trim();
      const validCategories = ['Điện thoại', 'Máy tính bảng', 'Phụ kiện'];

      if (validCategories.includes(categoryName)) {
        setTimeout(() => {
          // Tìm nút phân loại tương ứng trong phần #list sản phẩm
          const listSection = document.getElementById('list');
          if (listSection) {
            const categoryButtons = listSection.querySelectorAll('button');
            for (const btn of categoryButtons) {
              if (btn.textContent.trim() === categoryName) {
                btn.click();
                listSection.scrollIntoView({ behavior: 'smooth' });
                break;
              }
            }
          }
        }, 100);
      }
    });
  }

  // 6. Tự động đồng bộ giỏ hàng với LocalStorage
  function initCartPersistence() {
    try {
      const savedCart = localStorage.getItem('techno_store_cart');
      if (savedCart) {
        // Có thể lưu giữ giỏ hàng
      }
      // Lắng nghe khi có hành động đặt hàng thành công để gửi về API orders.php
      document.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        if (btn.textContent.includes('Xác nhận đặt hàng')) {
          // Gửi đơn hàng về Backend file-based
          fetch('/api/orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              customer: 'Khách hàng Demo (Đề tài 10)',
              phone: '0901234567',
              address: 'Mô phỏng đặt hàng từ giao diện',
              total: 34990000,
              items: [{ id: 1, name: 'Sản phẩm demo', qty: 1 }]
            })
          }).then(res => res.json()).then(data => {
            console.log('✅ Đơn hàng đã được lưu vào Backend (api/data/orders.json):', data);
          }).catch(err => console.log('Lưu đơn hàng:', err));
        }
      });
    } catch (err) {}
  }

  // 7. Thêm liên kết Cổng quản trị vào form Đăng nhập
  function initAdminLoginEntry() {
    setInterval(() => {
      const loginForm = document.querySelector('form.max-w-sm');
      if (loginForm && !document.getElementById('admin-login-entry')) {
        const linkDiv = document.createElement('div');
        linkDiv.id = 'admin-login-entry';
        linkDiv.style.cssText = 'text-align: center; margin-top: 16px; padding-top: 12px; border-top: 1px dashed #cbd5e1;';
        linkDiv.innerHTML = `
          <button type="button" id="btn-switch-admin" style="
            background: none; border: none; color: #475569; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
          " onmouseover="this.style.color='#1a6dff'" onmouseout="this.style.color='#475569'">
            🔒 Cổng đăng nhập dành cho Quản trị viên (Admin) →
          </button>
        `;
        loginForm.appendChild(linkDiv);

        document.getElementById('btn-switch-admin')?.addEventListener('click', () => {
          const footerButtons = document.querySelectorAll('footer button');
          for (const btn of footerButtons) {
            if (btn.textContent.includes('Cổng quản trị')) {
              btn.click();
              window.scrollTo({ top: 0, behavior: 'smooth' });
              break;
            }
          }
        });
      }
    }, 500);
  }

  // Khởi động khi DOM sẵn sàng
  window.addEventListener('DOMContentLoaded', () => {
    initProjectBadge();
    fetchServerInfo();
    initHeaderNavigation();
    initCartPersistence();
    initAdminLoginEntry();
  });
})();
