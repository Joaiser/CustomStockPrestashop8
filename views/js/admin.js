class ProductSearchManager {
  constructor() {
    this.currentPage = 1;
    this.limit = 50;
    this.searchTerm = '';
    this.isLoading = false;
    this.modifiedInputs = new Set();

    this.initializeElements();
    this.attachEvents();

    console.log('ProductSearchManager inicializado');
  }

  initializeElements() {
    this.searchInput = document.getElementById('product_search');
    this.rowsPerPage = document.getElementById('rows_per_page');
    this.clearSearch = document.getElementById('clear_search');
    this.productsTable = document.getElementById('products_table');
    this.visibleCount = document.getElementById('visible_count');
    this.totalCount = document.getElementById('total_count');
    this.selectAllBtn = document.getElementById('select_all');
    this.deselectAllBtn = document.getElementById('deselect_all');
    this.paginationContainer = document.getElementById('pagination_container');
    this.resultsCounter = document.getElementById('results_counter');
    this.stockForm = document.getElementById('stock_config_form');
    this.submitBtn = this.stockForm ? this.stockForm.querySelector('button[type="submit"]') : null;

    console.log('Elementos inicializados:', {
      searchInput: !!this.searchInput,
      productsTable: !!this.productsTable,
      stockForm: !!this.stockForm,
      submitBtn: !!this.submitBtn
    });
  }

  attachEvents() {
    if (this.searchInput) {
      this.searchInput.addEventListener('input', this.debounce(() => {
        console.log('Buscando:', this.searchInput.value);
        this.searchTerm = this.searchInput.value.trim();
        this.currentPage = 1;
        this.loadProducts();
      }, 500));
    }

    if (this.rowsPerPage) {
      this.rowsPerPage.addEventListener('change', () => {
        this.limit = parseInt(this.rowsPerPage.value);
        this.currentPage = 1;
        this.loadProducts();
      });
    }

    if (this.clearSearch) {
      this.clearSearch.addEventListener('click', () => {
        this.searchInput.value = '';
        this.searchTerm = '';
        this.currentPage = 1;
        this.loadProducts();
      });
    }

    if (this.selectAllBtn) {
      this.selectAllBtn.addEventListener('click', () => this.toggleAllInputs(true));
    }

    if (this.deselectAllBtn) {
      this.deselectAllBtn.addEventListener('click', () => this.toggleAllInputs(false));
    }

    // NUEVO: Manejar el envío del formulario con AJAX
    if (this.stockForm) {
      this.stockForm.addEventListener('submit', (e) => {
        e.preventDefault(); // Prevenir envío normal
        this.saveConfiguration();
      });
    }
  }

  attachInputListeners() {
    const inputs = this.productsTable.querySelectorAll('.stock-input');
    inputs.forEach(input => {
      // Guardar valor original
      input.dataset.originalValue = input.value;

      input.addEventListener('change', () => {
        if (input.value !== input.dataset.originalValue) {
          this.modifiedInputs.add(input.name);
          console.log(`Input modificado: ${input.name} = ${input.value}`);
        } else {
          this.modifiedInputs.delete(input.name);
        }
      });
    });
  }

  async saveConfiguration() {
    if (this.isLoading) return;

    this.isLoading = true;
    this.showSaving();

    try {
      console.log('Guardando configuración...');
      console.log('Inputs modificados:', Array.from(this.modifiedInputs));

      if (this.modifiedInputs.size === 0) {
        this.showSuccess('No hay cambios para guardar');
        return;
      }

      // Crear FormData solo con los modificados
      const formData = new FormData();

      // Añadir el token
      const tokenInput = this.stockForm.querySelector('input[name="token"]');
      if (tokenInput) {
        formData.append('token', tokenInput.value);
      }

      // Añadir action
      formData.append('action', 'saveConfiguration');

      // Recoger SOLO los inputs modificados
      const modifiedProducts = {};

      this.modifiedInputs.forEach(inputName => {
        const input = this.stockForm.querySelector(`input[name="${inputName}"]`);
        if (input) {
          const value = input.value || '0';

          // Extraer la clave del nombre (id_product o id_product_id_attribute)
          const match = inputName.match(/\[(.*?)\]/);
          if (match) {
            const key = match[1]; // Esto será "2043" o "2043_2715"
            const field = inputName.includes('stock_min') ? 'stock_min' : 'stock_display';

            if (!modifiedProducts[key]) {
              modifiedProducts[key] = {
                id_product: 0,
                id_product_attribute: 0,
                stock_min: 0,
                stock_display: 0
              };
            }

            // Parsear id_product y id_product_attribute
            const parts = key.split('_');
            modifiedProducts[key].id_product = parseInt(parts[0]);
            modifiedProducts[key].id_product_attribute = parts[1] ? parseInt(parts[1]) : 0;

            modifiedProducts[key][field] = parseInt(value);

            console.log(`✅ Modificado: ${inputName} = ${value} (Producto: ${modifiedProducts[key].id_product}, Atributo: ${modifiedProducts[key].id_product_attribute})`);
          }
        }
      });

      // Enviar en formato estructurado
      Object.values(modifiedProducts).forEach(product => {
        formData.append('products[]', JSON.stringify(product));
      });

      console.log(`📦 Enviando ${Object.keys(modifiedProducts).length} productos modificados:`, modifiedProducts);

      const response = await fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      console.log('Respuesta guardado:', response.status);

      const contentType = response.headers.get('content-type');
      let data;

      if (contentType && contentType.includes('application/json')) {
        data = await response.json();
      } else {
        const text = await response.text();
        console.error('Respuesta no JSON:', text);
        throw new Error(`Respuesta del servidor no válida: ${response.status}`);
      }

      if (data.success) {
        this.showSuccess('Configuración guardada correctamente');
        console.log('Configuración guardada exitosamente:', data);

        // Reset modified inputs después de guardar exitosamente
        this.modifiedInputs.clear();

        // Actualizar valores originales
        const inputs = this.productsTable.querySelectorAll('.stock-input');
        inputs.forEach(input => {
          input.dataset.originalValue = input.value;
        });
      } else {
        console.error('Error guardando configuración:', data);
        this.showError('Error guardando configuración: ' + (data.message || 'Error desconocido'));
      }
    } catch (error) {
      console.error('Error:', error);
      this.showError('Error de conexión: ' + error.message);
    } finally {
      this.isLoading = false;
      this.hideSaving();
    }
  }

  showSaving() {
    if (this.submitBtn) {
      this.submitBtn.disabled = true;
      this.submitBtn.innerHTML = '<i class="icon-spinner icon-spin"></i> Guardando...';
    }
  }

  hideSaving() {
    if (this.submitBtn) {
      this.submitBtn.disabled = false;
      this.submitBtn.innerHTML = '<i class="icon-save"></i> Guardar Configuración';
    }
  }

  showSuccess(message) {
    // Crear alerta de éxito
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible';
    alert.innerHTML = `
      <button type="button" class="close" data-dismiss="alert">&times;</button>
      <i class="icon-check"></i> ${message}
    `;

    // Insertar al inicio del panel
    const panel = document.querySelector('.panel');
    if (panel) {
      panel.insertBefore(alert, panel.firstChild);

      // Auto-eliminar después de 5 segundos
      setTimeout(() => {
        if (alert.parentNode) {
          alert.parentNode.removeChild(alert);
        }
      }, 5000);
    }
  }

  showError(message) {
    // Crear alerta de error
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible';
    alert.innerHTML = `
      <button type="button" class="close" data-dismiss="alert">&times;</button>
      <i class="icon-remove"></i> ${message}
    `;

    // Insertar al inicio del panel
    const panel = document.querySelector('.panel');
    if (panel) {
      panel.insertBefore(alert, panel.firstChild);

      // Auto-eliminar después de 5 segundos
      setTimeout(() => {
        if (alert.parentNode) {
          alert.parentNode.removeChild(alert);
        }
      }, 5000);
    }
  }

  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  async loadProducts() {
    if (this.isLoading) return;

    this.isLoading = true;
    this.showLoading();

    try {
      console.log('Cargando productos...', {
        search: this.searchTerm,
        page: this.currentPage,
        limit: this.limit
      });

      const formData = new FormData();
      formData.append('ajax', '1');
      formData.append('action', 'searchProducts');
      formData.append('search', this.searchTerm);
      formData.append('page', this.currentPage.toString());
      formData.append('limit', this.limit.toString());

      // Obtener el token del formulario
      const tokenInput = this.stockForm.querySelector('input[name="token"]');
      if (tokenInput) {
        formData.append('token', tokenInput.value);
      }

      const response = await fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      console.log('Respuesta recibida:', response.status);

      // Manejar diferentes tipos de respuesta
      const contentType = response.headers.get('content-type');
      let data;

      if (contentType && contentType.includes('application/json')) {
        data = await response.json();
      } else {
        const text = await response.text();
        console.error('Respuesta no JSON:', text);
        throw new Error(`Respuesta del servidor no válida: ${response.status}`);
      }

      console.log('Datos recibidos:', data);

      if (data.success) {
        this.renderProducts(data.products);
        this.renderPagination(data.pagination);
        this.updateCounter(data.pagination.total_products, data.products.length);
      } else {
        console.error('Error loading products:', data);
        this.showError('Error cargando productos: ' + (data.message || 'Error desconocido'));
      }
    } catch (error) {
      console.error('Error:', error);
      this.showError('Error de conexión: ' + error.message);
    } finally {
      this.isLoading = false;
      this.hideLoading();
    }
  }

  renderProducts(products) {
    const tbody = this.productsTable.querySelector('tbody');

    console.log("=== PRODUCTOS RECIBIDOS EN JS ===");
    products.forEach(product => {
      console.log(`Producto: ID=${product.id_product}, Attr=${product.id_product_attribute}, Type=${product.type}, Name=${product.name}`);
    });

    if (products.length === 0) {
      tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center">
                    ${this.searchTerm ? 'No se encontraron productos para "' + this.searchTerm + '"' : 'No se encontraron productos activos'}
                </td>
            </tr>
        `;
      return;
    }

    tbody.innerHTML = products.map(product => {
      const inputKey = product.id_product_attribute === 0 ?
        product.id_product.toString() :
        `${product.id_product}_${product.id_product_attribute}`;

      const typeBadge = product.type === 'combination' ?
        '<span class="badge badge-info" style="margin-left: 5px;">Combinación</span>' :
        '';

      return `
            <tr data-product-id="${product.id_product}" data-product-attribute="${product.id_product_attribute}">
                <td>
                    ${product.id_product}
                    ${product.id_product_attribute > 0 ? `-${product.id_product_attribute}` : ''}
                </td>
                <td class="product-name">
                    ${this.highlightText(product.name, this.searchTerm)}
                    ${typeBadge}
                </td>
                <td class="product-reference">
                    ${this.highlightText(product.reference, this.searchTerm)}
                    ${product.attribute_reference ? `<br><small>${this.highlightText(product.attribute_reference, this.searchTerm)}</small>` : ''}
                </td>
                <td>
                    <input type="number" 
                           name="stock_min[${inputKey}]" 
                           value="${product.stock_min}" 
                           min="0" 
                           class="form-control stock-input"
                           style="width: 100%;">
                </td>
                <td>
                    <input type="number" 
                           name="stock_display[${inputKey}]" 
                           value="${product.stock_display}" 
                           min="0" 
                           class="form-control stock-input"
                           style="width: 100%;">
                </td>
            </tr>
        `;
    }).join('');

    console.log(`Renderizados ${products.length} productos`);

    // Attach listeners después de renderizar
    setTimeout(() => this.attachInputListeners(), 100);
  }

  highlightText(text, searchTerm) {
    if (!searchTerm || !text) return text;

    const escapedTerm = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escapedTerm})`, 'gi');
    return text.replace(regex, '<mark class="highlight">$1</mark>');
  }

  renderPagination(pagination) {
    if (!this.paginationContainer) return;

    if (pagination.total_pages <= 1) {
      this.paginationContainer.innerHTML = '';
      return;
    }

    let paginationHTML = `
      <div class="row">
        <div class="col-md-6">
          <span>Página ${pagination.current_page} de ${pagination.total_pages} (Total: ${pagination.total_products} productos)</span>
        </div>
        <div class="col-md-6 text-right">
    `;

    // Botón anterior
    if (pagination.has_previous) {
      paginationHTML += `<button type="button" class="btn btn-default page-btn" data-page="${pagination.current_page - 1}">
        <i class="icon-chevron-left"></i> Anterior
      </button>`;
    }

    // Números de página
    const startPage = Math.max(1, pagination.current_page - 2);
    const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

    for (let i = startPage; i <= endPage; i++) {
      if (i === pagination.current_page) {
        paginationHTML += `<button type="button" class="btn btn-primary" disabled>${i}</button>`;
      } else {
        paginationHTML += `<button type="button" class="btn btn-default page-btn" data-page="${i}">${i}</button>`;
      }
    }

    // Botón siguiente
    if (pagination.has_next) {
      paginationHTML += `<button type="button" class="btn btn-default page-btn" data-page="${pagination.current_page + 1}">
        Siguiente <i class="icon-chevron-right"></i>
      </button>`;
    }

    paginationHTML += `
        </div>
      </div>
    `;

    this.paginationContainer.innerHTML = paginationHTML;

    // Attach events to page buttons
    this.paginationContainer.querySelectorAll('.page-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        this.currentPage = parseInt(e.target.dataset.page);
        this.loadProducts();
      });
    });
  }

  updateCounter(total, visible) {
    if (this.totalCount) this.totalCount.textContent = total;
    if (this.visibleCount) this.visibleCount.textContent = visible;
  }

  toggleAllInputs(enable) {
    const inputs = this.productsTable.querySelectorAll('.stock-input');
    inputs.forEach(input => {
      if (enable) {
        input.disabled = false;
        if (!input.value) {
          input.value = input.name.includes('stock_min') ? '0' : '0';
        }
      } else {
        input.disabled = true;
        input.value = '';
      }
    });
  }

  showLoading() {
    this.productsTable.classList.add('loading');
    const tbody = this.productsTable.querySelector('tbody');
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="text-center">
            <i class="icon-spinner icon-spin"></i> Cargando productos...
          </td>
        </tr>
      `;
    }
  }

  hideLoading() {
    this.productsTable.classList.remove('loading');
  }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function () {
  console.log('DOM cargado - Inicializando ProductSearchManager');
  new ProductSearchManager();
});