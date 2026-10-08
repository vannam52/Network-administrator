function adminApp() {
    return {
        currentTab: 'dashboard',
        loading: false,
        tabs: [
            { id: 'dashboard', name: 'Tổng quan', icon: 'grid' },
            { id: 'products', name: 'Sản phẩm', icon: 'package' },
            { id: 'orders', name: 'Đơn hàng', icon: 'shopping-cart' }
        ],
        stats: { revenue: 0, orders: 0, lowStock: 0 },
        products: [],
        orders: [],
        searchProduct: '',
        showProductModal: false,
        editProduct: null,
        form: { id: null, name: '', price: 0, stock: 10, category: 'iPhone 17 Series' },

        initApp() {
            feather.replace();
            this.fetchData();
        },

        async fetchData() {
            this.loading = true;
            try {
                const [dashRes, prodRes, orderRes] = await Promise.all([
                    fetch('/api/dashboard.php').then(r => r.json()),
                    fetch('/api/products.php').then(r => r.json()),
                    fetch('/api/orders.php').then(r => r.json())
                ]);
                this.stats = dashRes;
                this.products = Array.isArray(prodRes) ? prodRes : (prodRes.data || []);
                this.orders = Array.isArray(orderRes) ? orderRes : (orderRes.data || []);
            } catch (error) {
                console.error("Lỗi tải dữ liệu", error);
            } finally {
                this.loading = false;
            }
        },

        get filteredProducts() {
            if (!this.searchProduct) return this.products;
            const s = this.searchProduct.toLowerCase();
            return this.products.filter(p => p.name.toLowerCase().includes(s) || (p.code && p.code.toLowerCase().includes(s)));
        },

        formatMoney(amount) {
            return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
        },

        resetForm() {
            this.form = { id: null, name: '', price: 0, stock: 10, category: 'iPhone 17 Series' };
        },

        openEditModal(p) {
            this.editProduct = p;
            this.form = { ...p };
            this.showProductModal = true;
        },

        async saveProduct() {
            this.loading = true;
            try {
                const method = this.form.id ? 'PUT' : 'POST';
                const apiUrl = '/api/products.php';
                const res = await fetch(apiUrl, {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form)
                });
                
                if (res.ok) {
                    await this.fetchData();
                    this.showProductModal = false;
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.loading = false;
            }
        },

        async deleteOrder(id) {
            if (!confirm(`Bạn có chắc chắn muốn xóa đơn hàng #${id}?`)) return;
            this.loading = true;
            try {
                const res = await fetch(`/api/orders.php?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
                if (res.ok) {
                    await this.fetchData();
                }
            } catch (err) {
                console.error('Lỗi khi xóa đơn hàng:', err);
            } finally {
                this.loading = false;
            }
        }
    }
}

