import { ref, computed } from 'vue';
import { productApi } from '@/api';

export function useProducts() {
  const products = ref([]);
  const loading = ref(false);
  const error = ref(null);
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  });

  const fetchProducts = async (params = {}) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await productApi.getAll(params);
      products.value = response.data.data || response.data;
      
      if (response.data.meta) {
        pagination.value = response.data.meta;
      }
      
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Error fetching products';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const getProductById = async (id) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await productApi.get(id);
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Error fetching product';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const createProduct = async (data) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await productApi.create(data);
      await fetchProducts();
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.errors || err.response?.data?.message || 'Error creating product';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const updateProduct = async (id, data) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await productApi.update(id, data);
      await fetchProducts();
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.errors || err.response?.data?.message || 'Error updating product';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const deleteProduct = async (id) => {
    loading.value = true;
    error.value = null;

    try {
      await productApi.delete(id);
      await fetchProducts();
      return true;
    } catch (err) {
      error.value = err.response?.data?.message || 'Error deleting product';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const searchProducts = async (query) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await productApi.search(query);
      products.value = response.data.data || response.data;
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Error searching products';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const lowStockProducts = computed(() => {
    return products.value.filter(p => p.total_stock <= p.min_stock);
  });

  return {
    products,
    loading,
    error,
    pagination,
    fetchProducts,
    getProductById,
    createProduct,
    updateProduct,
    deleteProduct,
    searchProducts,
    lowStockProducts,
  };
}
