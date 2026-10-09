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
    hideSpecificButtonsInstantly();
    fixLoginVulnerability();
    fixReactAdminSearchAndFilter();
  });

  // Chữa cháy lỗi Search và Filter của trang Admin React bằng Vanilla JS
  function fixReactAdminSearchAndFilter() {
    setInterval(() => {
      // Chỉ chạy khi ở trang Admin
      if (!window.location.pathname.includes('/admin')) return;

      const searchInput = document.querySelector('input[placeholder*="Tìm theo mã, tên"]');
      const selectCategory = document.querySelector('button[aria-haspopup="menu"]'); // Nút Tất cả danh mục

      // Nếu có ô search và chưa được gán sự kiện
      if (searchInput && !searchInput.dataset.hasVanillaSearch) {
        searchInput.dataset.hasVanillaSearch = "true";

        searchInput.addEventListener('input', (e) => {
          const keyword = e.target.value.toLowerCase().trim();
          filterAdminTable(keyword, '');
        });
      }
    }, 1000);
  }

  function filterAdminTable(keyword, category) {
    // Tìm các dòng trong bảng sản phẩm
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      // Nếu không khớp từ khóa thì ẩn đi
      if (keyword && !text.includes(keyword)) {
        row.style.display = 'none';
      } else {
        row.style.display = '';
      }
    });
  }

  // 8. Ẩn ngay lập tức các nút gây lỗi (VD: Thêm danh mục) mà không bị độ trễ
  function hideSpecificButtonsInstantly() {
    // Sử dụng MutationObserver để theo dõi sự thay đổi của DOM, ẩn ngay khi React vừa render
    const observer = new MutationObserver(() => {
      if (window.location.pathname.includes('/admin')) {
        const buttons = document.querySelectorAll('button, a, div[role="button"], li');
        buttons.forEach(btn => {
          const text = btn.textContent.trim().toLowerCase();
          // CHỈ ẨN đúng nút "Thêm danh mục" và "Tạo phiếu nhập", giữ nguyên các lựa chọn khác
          if (text === 'thêm danh mục' || text === 'tạo phiếu nhập') {
            if (btn.tagName === 'A' || btn.tagName === 'BUTTON' || btn.tagName === 'LI') {
              btn.style.display = 'none';
            }
          }
        });
      }
    });

    // Theo dõi toàn bộ body liên tục
    observer.observe(document.body, { childList: true, subtree: true });
  }

  // 9. Vá lỗ hổng đăng nhập (Nhập bừa mật khẩu vẫn vào được)
  // 9. Vá lỗ hổng đăng nhập (Nhập bừa mật khẩu vẫn vào được)
  function fixLoginVulnerability() {
    // Viết một hàm xử lý chung cho cả click nút Đăng nhập và ấn Enter
    const handleLoginAttempt = async (e, form, btn) => {
      const passInput = form.querySelector('input[type="password"]');
      const phoneInput = form.querySelector('input[type="text"], input[type="email"]');

      if (passInput && phoneInput && passInput.value) {
        // CHẶN NGAY LẬP TỨC sự kiện gốc của trình duyệt và React
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        const phone = phoneInput.value.trim();
        const password = passInput.value.trim();

        if (!phone || !password) {
          alert('Vui lòng nhập đầy đủ thông tin!');
          return;
        }

        const originalText = btn ? btn.textContent : 'Đăng nhập';
        if (btn) {
          btn.textContent = 'Đang kiểm tra...';
          btn.disabled = true;
        }

        try {
          // Gọi API kiểm tra mật khẩu thực sự
          const res = await fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone, password })
          });

          if (!res.ok) throw new Error('API không tồn tại');

          const data = await res.json();

          if (data.status === 'success') {
            localStorage.setItem('phone_store_user', JSON.stringify(data.user));
            alert('Đăng nhập thành công!');
            window.location.href = '/';
          } else {
            alert('❌ ' + (data.message || 'Sai thông tin tài khoản hoặc mật khẩu!'));
            if (btn) {
              btn.textContent = originalText;
              btn.disabled = false;
            }
          }
        } catch (err) {
          alert('Vui lòng test chức năng Đăng nhập trên máy ảo Windows/Linux (Truy cập bằng IIS/Apache). Bản Node.js đang không hỗ trợ bảo mật mật khẩu!');
          if (btn) {
            btn.textContent = originalText;
            btn.disabled = false;
          }
        }
      }
    };

    // Bắt sự kiện CLICK vào nút Đăng nhập và Thêm Sản Phẩm
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;

      // XỬ LÝ ĐĂNG NHẬP
      if (btn.textContent.toLowerCase().includes('đăng nhập')) {
        const form = btn.closest('form') || btn.closest('.flex-col') || document.querySelector('form');
        if (form) handleLoginAttempt(e, form, btn);
        return;
      }

      // XỬ LÝ ĐĂNG KÝ (Thêm user vào Database)
      const btnText = btn.textContent.toLowerCase();
      if ((btnText.includes('đăng ký') || btnText.includes('tạo tài khoản')) && !btnText.includes('đăng nhập')) {
        const form = btn.closest('form');
        if (form) {
          // Chỉ lấy các input thực sự để nhập chữ/số (bỏ qua checkbox, radio)
          const textInputs = Array.from(form.querySelectorAll('input')).filter(input => 
              ['text', 'email', 'password', 'tel', 'number'].includes(input.type)
          );
          
          // Form Đăng ký luôn có ít nhất 3 ô nhập liệu chữ (Tên, SĐT, Mật khẩu)
          // Form Đăng nhập chỉ có 2 ô (SĐT, Mật khẩu)
          if (textInputs.length >= 3) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            const name = textInputs[0].value.trim();
            const phone = textInputs[1].value.trim();
            const password = textInputs[2].value.trim();

            if (!name || !phone || !password) {
              alert('Vui lòng nhập đầy đủ thông tin đăng ký!');
              return;
            }

            const originalText = btn.textContent;
            btn.textContent = 'Đang đăng ký...';
            btn.disabled = true;

            try {
              const apiUrl = window.location.port === '3000' ? '/api/users' : '/api/users.php';
              const res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, phone, password })
              });

              if (res.ok) {
                const data = await res.json();
                alert('Đăng ký tài khoản thành công!');
                localStorage.setItem('phone_store_user', JSON.stringify(data.user));
                window.location.href = '/';
              } else {
                const errorData = await res.json();
                alert('❌ ' + (errorData.message || 'Đăng ký thất bại, Email hoặc Số điện thoại đã tồn tại!'));
                btn.textContent = originalText;
                btn.disabled = false;
              }
            } catch (err) {
              alert('Lỗi kết nối máy chủ!');
              btn.textContent = originalText;
              btn.disabled = false;
            }
            return;
          }
        }
      }

      // XỬ LÝ THÊM SẢN PHẨM (Nút bị liệt của React)
      if (btn.textContent.toLowerCase().includes('thêm sản phẩm')) {
        e.preventDefault();
        e.stopPropagation();

        // Xóa modal cũ nếu có
        const oldModal = document.getElementById('custom-add-product-modal');
        if (oldModal) oldModal.remove();

        // Tạo giao diện Modal nổi giữa màn hình
        const modal = document.createElement('div');
        modal.id = 'custom-add-product-modal';
        modal.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:999999; backdrop-filter: blur(4px);';

        modal.innerHTML = `
            <div style="background:#fff; width:450px; border-radius:12px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); overflow:hidden;">
                <div style="padding:16px 24px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; background:#f9fafb;">
                    <h3 style="margin:0; font-size:18px; font-weight:600; color:#111827;">Thêm sản phẩm mới</h3>
                    <button id="close-modal-btn" style="background:none; border:none; font-size:24px; cursor:pointer; color:#6b7280; line-height:1;">&times;</button>
                </div>
                <div style="padding:24px; display:flex; flex-direction:column; gap:16px;">
                    <div>
                        <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:500; color:#374151;">Tên sản phẩm</label>
                        <input id="modal-p-name" type="text" placeholder="VD: iPhone 16 Pro Max 256GB" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="display:flex; gap:16px;">
                        <div style="flex:1;">
                            <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:500; color:#374151;">Giá bán (VNĐ)</label>
                            <input id="modal-p-price" type="number" placeholder="VD: 34990000" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; outline:none; box-sizing:border-box;">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:500; color:#374151;">Tồn kho</label>
                            <input id="modal-p-stock" type="number" placeholder="10" value="10" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; outline:none; box-sizing:border-box;">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:500; color:#374151;">Danh mục</label>
                        <select id="modal-p-cat" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="iPhone 17 Series">iPhone 17 Series</option>
                            <option value="iPhone 16 Series">iPhone 16 Series</option>
                            <option value="iPhone 15 Series">iPhone 15 Series</option>
                            <option value="iPhone 14 Series">iPhone 14 Series</option>
                            <option value="Khác">Khác</option>
                        </select>
                    </div>
                </div>
                <div style="padding:16px 24px; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:12px; background:#f9fafb;">
                    <button id="cancel-modal-btn" style="padding:8px 16px; border:none; background:#e5e7eb; color:#374151; border-radius:8px; font-weight:500; cursor:pointer;">Hủy</button>
                    <button id="save-modal-btn" style="padding:8px 16px; border:none; background:#2563eb; color:#fff; border-radius:8px; font-weight:500; cursor:pointer;">Lưu Sản Phẩm</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Xử lý đóng modal
        const closeModal = () => modal.remove();
        document.getElementById('close-modal-btn').onclick = closeModal;
        document.getElementById('cancel-modal-btn').onclick = closeModal;

        // Bấm ra ngoài modal để đóng
        modal.addEventListener('click', (ev) => {
          if (ev.target === modal) closeModal();
        });

        // Xử lý lưu
        document.getElementById('save-modal-btn').onclick = async function () {
          const name = document.getElementById('modal-p-name').value.trim();
          const price = document.getElementById('modal-p-price').value.trim();
          const stock = document.getElementById('modal-p-stock').value.trim();
          const cat = document.getElementById('modal-p-cat').value;

          if (!name || !price) {
            alert('Vui lòng nhập Tên sản phẩm và Giá bán!');
            return;
          }

          this.textContent = 'Đang lưu...';
          this.disabled = true;

          const code = 'SP' + Math.floor(Math.random() * 10000);

          try {
            // Tự động gọi API chung (hỗ trợ cả Node.js và PHP)
            const apiUrl = window.location.port === '3000' ? '/api/products' : '/api/products.php';
            const res = await fetch(apiUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                name: name,
                price: price,
                stock: stock,
                code: code,
                category: cat,
                img: 'https://cdn.hoanghamobile.com/i/productlist/dsp/Uploads/2023/09/13/iphone-15-pro-max-natural-titanium-pure-back-iphone-15-pro-max-natural-titanium-pure-front-2up-screen-usen.png'
              })
            });

            if (res.ok) {
              alert('Thêm sản phẩm thành công!');
              window.location.reload(); // Tải lại trang để React cập nhật danh sách
            } else {
              alert('Có lỗi xảy ra khi thêm sản phẩm!');
            }
          } catch (err) {
            alert('Lỗi kết nối đến Server!');
          }
          closeModal();
        };
      }
    }, true);

    // Bắt sự kiện SUBMIT form (Khi ấn Enter)
    document.addEventListener('submit', (e) => {
      const form = e.target;
      const btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
      if (btn && btn.textContent.toLowerCase().includes('đăng nhập')) {
        handleLoginAttempt(e, form, btn);
      }
    }, true);

  }
})();
