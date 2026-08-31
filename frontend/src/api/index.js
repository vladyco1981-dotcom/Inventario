import apiClient from './client';

export const productApi = {
  getAll(params = {}) {
    return apiClient.get('/products', { params });
  },

  get(id) {
    return apiClient.get(`/products/${id}`);
  },

  create(data) {
    return apiClient.post('/products', data);
  },

  update(id, data) {
    return apiClient.put(`/products/${id}`, data);
  },

  delete(id) {
    return apiClient.delete(`/products/${id}`);
  },

  search(query) {
    return apiClient.get('/products/search', { params: { q: query } });
  },

  getStock(id) {
    return apiClient.get(`/products/${id}/stock`);
  },
};

export const categoryApi = {
  getAll(params = {}) {
    return apiClient.get('/categories', { params });
  },

  get(id) {
    return apiClient.get(`/categories/${id}`);
  },

  create(data) {
    return apiClient.post('/categories', data);
  },

  update(id, data) {
    return apiClient.put(`/categories/${id}`, data);
  },

  delete(id) {
    return apiClient.delete(`/categories/${id}`);
  },
};

export const orderApi = {
  getAll(params = {}) {
    return apiClient.get('/orders', { params });
  },

  get(id) {
    return apiClient.get(`/orders/${id}`);
  },

  create(data) {
    return apiClient.post('/orders', data);
  },

  confirm(id) {
    return apiClient.post(`/orders/${id}/confirm`);
  },

  complete(id) {
    return apiClient.post(`/orders/${id}/complete`);
  },

  cancel(id) {
    return apiClient.post(`/orders/${id}/cancel`);
  },

  addPayment(id, data) {
    return apiClient.post(`/orders/${id}/payments`, data);
  },
};

export const dashboardApi = {
  getStats() {
    return apiClient.get('/dashboard');
  },

  getLowStockProducts() {
    return apiClient.get('/dashboard/low-stock');
  },

  getExpiringProducts() {
    return apiClient.get('/dashboard/expiring-soon');
  },
};

export const authApi = {
  login(credentials) {
    return apiClient.post('/login', credentials);
  },

  register(data) {
    return apiClient.post('/register', data);
  },

  logout() {
    return apiClient.post('/logout');
  },

  getUser() {
    return apiClient.get('/user');
  },
};
