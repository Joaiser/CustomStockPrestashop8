/**
 * Registered Products Management
 * Maneja la tabla de productos con configuración personalizada
 */
class RegisteredProductsManager {
  constructor() {
    this.currentPage = 1;
    this.currentSearch = '';
    this.selectedIds = new Set();
    this.isLoading = false;
    this.elements = {};

    this.init();
  }

  init() {
    this.cacheElements();
    this.bindEvents();
    this.loadRegisteredProducts();
  }

  cacheElements() {
    this.elements = {
      searchInput: document.getElementById('registered_search'),
      searchButton: document.getElementById('search_registered'),
      clearSearchButton: document.getElementById('clear_registered_search'),
      selectAllCheckbox: document.getElementById('select_all_registered'),
      refreshButton: document.getElementById('refresh_registered'),
      bulkDeleteButton: document.getElementById('bulk_delete'),
      productsBody: document.getElementById('registered_products_body'),
      paginationContainer: document.getElementById('registered_pagination'),
      resultsCounter: document.getElementById('registered_results_counter')
    };
  }

  bindEvents() {
    // Búsqueda
    this.elements.searchButton.addEventListener('click', () => this.handleSearch());
    this.elements.searchInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') this.handleSearch();
    });
    this.elements.clearSearchButton.addEventListener('click', () => this.clearSearch());

    // Selección
    this.elements.selectAllCheckbox.addEventListener('change', (e) => this.toggleSelectAll(e));

    // Acciones
    this.elements.refreshButton.addEventListener('click', () => this.refresh());
    this.elements.bulkDeleteButton.addEventListener('click', () => this.bulkDelete());
  }

  handleSearch() {
    this.currentSearch = this.elements.searchInput.value.trim();
    this.currentPage = 1;
    this.loadRegisteredProducts();
  }

  clearSearch() {
    this.elements.searchInput.value = '';
    this.currentSearch = '';
    this.currentPage = 1;
    this.loadRegisteredProducts();
  }

  toggleSelectAll(e) {
    const isChecked = e.target.checked;
    const checkboxes = document.querySelectorAll('.product-checkbox');

    checkboxes.forEach(checkbox => {
      checkbox.checked = isChecked;
      const id = parseInt(checkbox.dataset.id);

      if (isChecked) {
        this.selectedIds.add(id);
      } else {
        this.selectedIds.delete(id);
      }
    });

    this.updateBulkDeleteButton();
  }

  updateBulkDeleteButton() {
    const hasSelection = this.selectedIds.size > 0;
    this.elements.bulkDeleteButton.disabled = !hasSelection;
  }

  async loadRegisteredProducts() {
    if (this.isLoading) return;

    this.isLoading = true;
    this.showLoading();

    try {
      const formData = new FormData();
      formData.append('page', this.currentPage);
      formData.append('search', this.currentSearch);
      formData.append('limit', 50);

      const response = await fetch(this.getAjaxUrl('getRegisteredProducts'), {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        this.renderProducts(data.products);
        this.renderPagination(data.pagination);
        this.updateResultsCounter(data.pagination);
      } else {
        this.showError('Error al cargar productos: ' + data.error);
      }
    } catch (error) {
      this.showError('Error de conexión: ' + error.message);
    } finally {
      this.isLoading = false;
    }
  }

  renderProducts(products) {
    if (products.length === 0) {
      this.elements.productsBody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center">
                        <div class="alert alert-info">
                            <i class="icon-info-circle"></i>
                            No se encontraron productos con configuración personalizada.
                        </div>
                    </td>
                </tr>
            `;
      return;
    }

    const rows = products.map(product => this.createProductRow(product));
    this.elements.productsBody.innerHTML = rows.join('');

    // Re-bind events para los checkboxes y botones de esta página
    this.bindRowEvents();
  }

  createProductRow(product) {
    const typeBadge = product.type === 'product' ?
      '<span class="badge badge-product">Producto</span>' :
      '<span class="badge badge-combination">Combinación</span>';

    const reference = product.attribute_reference || product.product_reference;
    const isSelected = this.selectedIds.has(product.id_customstock);
    const attributeInfo = product.id_product_attribute > 0 ?
      `<br><small>ID Atributo: ${product.id_product_attribute}</small>` : '';

    return `
            <tr data-id="${product.id_customstock}" 
                data-product-id="${product.id_product}"
                data-product-name="${this.escapeHtml(product.display_name)}"
                data-stock-min="${product.stock_min}"
                data-stock-display="${product.stock_display}">
                <td>
                    <input type="checkbox" class="product-checkbox" 
                           data-id="${product.id_customstock}"
                           ${isSelected ? 'checked' : ''}>
                </td>
                <td>${product.id_product}</td>
                <td>
                    <strong>${this.escapeHtml(product.display_name)}</strong>
                    ${attributeInfo}
                </td>
                <td>${typeBadge}</td>
                <td>${this.escapeHtml(reference)}</td>
                <td class="text-center">
                    <span class="badge badge-warning">${product.stock_min}</span>
                </td>
                <td class="text-center">
                    <span class="badge badge-success">+${product.stock_display}</span>
                </td>
                <td>
                    <small>${this.formatDate(product.date_upd)}</small>
                </td>
                <td class="action-buttons">
                    <button type="button" class="btn btn-default btn-sm edit-product" 
                            data-id="${product.id_customstock}" title="Editar">
                        <i class="icon-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm delete-product" 
                            data-id="${product.id_customstock}" title="Eliminar">
                        <i class="icon-trash"></i>
                    </button>
                </td>
            </tr>
        `;
  }

  bindRowEvents() {
    // Checkboxes individuales
    document.querySelectorAll('.product-checkbox').forEach(checkbox => {
      checkbox.addEventListener('change', (e) => {
        const id = parseInt(e.target.dataset.id);

        if (e.target.checked) {
          this.selectedIds.add(id);
        } else {
          this.selectedIds.delete(id);
          this.elements.selectAllCheckbox.checked = false;
        }

        this.updateBulkDeleteButton();
      });
    });

    // Botones de acción
    document.querySelectorAll('.edit-product').forEach(button => {
      button.addEventListener('click', (e) => this.editProduct(e));
    });

    document.querySelectorAll('.delete-product').forEach(button => {
      button.addEventListener('click', (e) => this.deleteProduct(e));
    });
  }

  renderPagination(pagination) {
    if (pagination.total_pages <= 1) {
      this.elements.paginationContainer.innerHTML = '';
      return;
    }

    let html = '<ul class="pagination pagination-sm">';

    // Botón anterior
    if (pagination.has_previous) {
      html += `
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${pagination.current_page - 1}">
                        &laquo; Anterior
                    </a>
                </li>
            `;
    }

    // Páginas
    for (let i = 1; i <= pagination.total_pages; i++) {
      const isActive = i === pagination.current_page;
      html += `
                <li class="page-item ${isActive ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
    }

    // Botón siguiente
    if (pagination.has_next) {
      html += `
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${pagination.current_page + 1}">
                        Siguiente &raquo;
                    </a>
                </li>
            `;
    }

    html += '</ul>';
    this.elements.paginationContainer.innerHTML = html;

    // Bind events de paginación
    this.elements.paginationContainer.querySelectorAll('.page-link').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const page = parseInt(e.target.dataset.page);
        this.goToPage(page);
      });
    });
  }

  updateResultsCounter(pagination) {
    const start = ((pagination.current_page - 1) * 50) + 1;
    const end = Math.min(start + 49, pagination.total_products);

    this.elements.resultsCounter.innerHTML = `
            Mostrando ${start}-${end} de ${pagination.total_products} configuraciones
        `;
  }

  goToPage(page) {
    this.currentPage = page;
    this.loadRegisteredProducts();
  }

  refresh() {
    this.currentPage = 1;
    this.loadRegisteredProducts();
    this.showSuccess('Lista actualizada correctamente');
  }

  editProduct(e) {
    const id = e.currentTarget.dataset.id;
    const row = e.currentTarget.closest('tr')


    const productData = {
      id_customstock: id,
      id_product: row.dataset.productId,
      display_name: row.dataset.productName,
      stock_min: parseInt(row.dataset.stockMin),
      stock_display: parseInt(row.dataset.stockDisplay)
    }

    this.showEditModal(productData)
  }

  showEditModal(productData) {
    const modalHtml = `
        <div class="modal fade" id="editProductModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">
                            <i class="icon-pencil"></i> Editar Configuración de Stock
                        </h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-horizontal">
                            <div class="form-group">
                                <label class="control-label col-sm-4">Producto:</label>
                                <div class="col-sm-8">
                                    <p class="form-control-static">${this.escapeHtml(productData.display_name)}</p>
                                    <small class="text-muted">ID: ${productData.id_product}</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_stock_min" class="control-label col-sm-4">
                                    Stock Mínimo:
                                </label>
                                <div class="col-sm-8">
                                    <input type="number" 
                                           id="edit_stock_min" 
                                           class="form-control" 
                                           value="${productData.stock_min}" 
                                           min="0"
                                           required>
                                    <small class="help-block">
                                        Cuando el stock real sea igual o superior a este valor, se mostrará el "Stock a Mostrar"
                                    </small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_stock_display" class="control-label col-sm-4">
                                    Stock a Mostrar:
                                </label>
                                <div class="col-sm-8">
                                    <input type="number" 
                                           id="edit_stock_display" 
                                           class="form-control" 
                                           value="${productData.stock_display}" 
                                           min="0"
                                           required>
                                    <small class="help-block">
                                        Valor que se mostrará cuando el stock real supere el mínimo (ej: +100)
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">
                            <i class="icon-remove"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" id="saveEditProduct">
                            <i class="icon-save"></i> Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    const existingModal = document.getElementById('editProductModal');
    if (existingModal) {
      existingModal.remove();
    }


    document.body.insertAdjacentHTML('beforeend', modalHtml)

    const modal = document.getElementById('editProductModal');
    $(modal).modal('show')

    this.bindEditModalEvents(modal, productData)
  }

  bindEditModalEvents(modal, productData) {
    const saveButton = modal.querySelector('#saveEditProduct')
    const stockMinInput = modal.querySelector('#edit_stock_min')
    const stockDisplayInput = modal.querySelector('#edit_stock_display')

    saveButton.addEventListener('click', async () => {
      const stockMin = parseInt(stockMinInput.value)
      const stockDisplay = parseInt(stockDisplayInput.value)

      if (isNaN(stockMin) || stockMin < 0) {
        this.showError('El stock mínimo debe de ser un numero positivo')
        return;
      }

      if (isNaN(stockDisplay) || stockDisplay < 0) {
        this.showError('El stock a mostrar debe de ser un número positivo')
        return
      }

      // Durante la petición deshabilitamos button
      saveButton.disabled = true
      saveButton.innerHTML = '<i class="icon-spinner icon-spin"></i> Guardando...'

      try {
        await this.saveProductEdit(productData.id_customstock, stockMin, stockDisplay)
        $(modal).modal('hide')
      } catch (error) {
        this.showError('Error al guardar: ' + error.message) // A ver el error que puede devolver, aun que es admin...
      } finally {
        saveButton.disabled = false
        saveButton.innerHTML = '<i class="icon-save"></i> Guardar cambios'
      }
    })
    // Cerrar modal
    modal.querySelector('.close').addEventListener('click', () => {
      $(modal).modal('hide');
    });

    // Limpiar el modal al cerrarlo
    $(modal).on('hidden.bs.modal', () => {
      modal.remove()
    })

    // Enter para guardar
    modal.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        saveButton.click()
      }
    })
  }

  async saveProductEdit(id_customstock, stock_min, stock_display) {
    try {
      const formData = new FormData()
      formData.append('id_customstock', id_customstock);
      formData.append('stock_min', stock_min);
      formData.append('stock_display', stock_display);

      const response = await fetch(this.getAjaxUrl('updateConfiguration'), {
        method: 'POST',
        body: formData
      })

      const data = await response.json()

      if (data.success) {
        this.showSuccess(data.message)
        this.loadRegisteredProducts()
      } else {
        throw new Error(data.error);

      }
    } catch (error) {
      throw new Error('Error de conexión: ' + error.message);

    }
  }


  async deleteProduct(e) {
    const id = e.currentTarget.dataset.id;
    const productName = e.currentTarget.closest('tr').querySelector('strong').textContent;

    if (!confirm(`¿Estás seguro de que quieres eliminar la configuración para "${productName}"?`)) {
      return;
    }

    try {
      const formData = new FormData();
      formData.append('id_customstock', id);

      const response = await fetch(this.getAjaxUrl('deleteConfiguration'), {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        this.showSuccess(data.message);
        this.loadRegisteredProducts();
        this.selectedIds.delete(id);
        this.updateBulkDeleteButton();
      } else {
        this.showError('Error al eliminar: ' + data.error);
      }
    } catch (error) {
      this.showError('Error de conexión: ' + error.message);
    }
  }

  async bulkDelete() {
    if (this.selectedIds.size === 0) return;

    if (!confirm(`¿Estás seguro de que quieres eliminar ${this.selectedIds.size} configuraciones?`)) {
      return;
    }

    try {
      // En PrestaShop, normalmente se espera 'ids[]' para arrays
      const formData = new FormData();
      const idsArray = Array.from(this.selectedIds);

      idsArray.forEach(id => {
        formData.append('ids[]', id);
      });

      console.log('Enviando IDs:', idsArray);

      const response = await fetch(this.getAjaxUrl('bulkDelete'), {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        this.showSuccess(data.message);
        this.selectedIds.clear();
        this.updateBulkDeleteButton();
        this.loadRegisteredProducts();
      } else {
        this.showError('Error al eliminar: ' + data.error);
      }
    } catch (error) {
      this.showError('Error de conexión: ' + error.message);
    }
  }

  // Helpers
  getAjaxUrl(action) {
    return `${window.customStockAdminUrl}&action=${action}&ajax=1&token=${window.customStockAdminToken}`;
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES');
  }

  showLoading() {
    this.elements.productsBody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center">
                    <div class="loading-spinner">
                        <i class="icon-spinner icon-spin icon-2x"></i>
                        <p>Cargando productos registrados...</p>
                    </div>
                </td>
            </tr>
        `;
  }

  showSuccess(message) {
    // Usar el sistema de notificaciones de PrestaShop si existe
    if (typeof showSuccessMessage !== 'undefined') {
      showSuccessMessage(message);
    } else {
      this.showNativeNotification(message, 'success');
    }
  }

  showError(message) {
    if (typeof showErrorMessage !== 'undefined') {
      showErrorMessage(message);
    } else {
      this.showNativeNotification(message, 'error');
    }
  }

  showWarning(message) {
    if (typeof showWarningMessage !== 'undefined') {
      showWarningMessage(message);
    } else {
      this.showNativeNotification(message, 'warning');
    }
  }

  showNativeNotification(message, type = 'info') {
    // Crear notificación nativa si no hay sistema de PrestaShop
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible`;
    notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
        `;
    notification.innerHTML = `
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            ${message}
        `;

    document.body.appendChild(notification);

    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 5000);

    // Cerrar al hacer click
    notification.querySelector('.close').addEventListener('click', () => {
      notification.parentNode.removeChild(notification);
    });
  }
}

// Inicialización cuando el DOM está listo
document.addEventListener('DOMContentLoaded', () => {
  window.registeredProductsManager = new RegisteredProductsManager();
});