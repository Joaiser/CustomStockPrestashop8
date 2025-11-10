/**
 * Stock Display Frontend Manager - Versión Mejorada para AJAX
 * Maneja la visualización del stock con formato personalizado
 */

class StockDisplayManager {
  constructor(stockConfig) {
    this.stockConfig = stockConfig;
    this.currentCombinationId = 0;
    this.initialized = false;
    this.observer = null;
    this.isApplyingFormat = false;
    this.stockElement = null;
    this.hasAppliedFormat = false;
    this.originalStockValue = null;

    this.initialize();
  }

  initialize() {
    if (this.initialized) return;
    this.initialized = true;

    // ✅ Asegurarnos de tener el elemento de stock antes de continuar
    if (!this.stockElement) {
      this.stockElement = this.getStockElement();
    }

    if (this.stockElement) {
      this.applyStockFormat();
      this.initializeObserver();
      this.interceptPrestaShopAjax();
      this.startSafePolling();
      this.interceptAttributeChanges();
    } else {
      console.warn('❌ No se encontró elemento de stock inicial, reintentando...');
      // Reintentar después de 1 segundo
      setTimeout(() => this.initialize(), 1000);
    }
  }

  // NUEVO MÉTODO: Reinicializar completamente después de cambios AJAX
  reinitializeAfterAjax() {
    // console.log('🔄 Reinicializando después de AJAX...');

    // Resetear todo
    this.hasAppliedFormat = false;
    this.originalStockValue = null;

    // Buscar el elemento de stock nuevamente
    this.stockElement = this.getStockElement();

    if (this.stockElement) {
      // console.log('✅ Nuevo elemento de stock encontrado después de AJAX');
      this.hideStock();
      this.applyStockFormat();
      this.initializeObserver();
    } else {
      console.warn('❌ No se encontró el elemento de stock después de AJAX, reintentando...');
      // Reintentar después de 500ms
      setTimeout(() => this.reinitializeAfterAjax(), 500);
    }
  }

  hideStock() {
    if (this.stockElement) {
      this.stockElement.style.opacity = '0';
      this.stockElement.style.visibility = 'hidden';
      // console.log('📦 Stock ocultado temporalmente');
    }
  }

  showStock() {
    if (this.stockElement) {
      this.stockElement.classList.add('stock-visible');
      this.stockElement.style.setProperty('opacity', '1', 'important');
      this.stockElement.style.setProperty('visibility', 'visible', 'important');
      this.stockElement.style.setProperty('display', 'block', 'important');
      // console.log('📦 Stock mostrado');
    }
  }

  getCombinationId() {
    let combId = 0;

    const combinationInput = document.getElementById('idCombination');
    if (combinationInput && combinationInput.value) {
      combId = parseInt(combinationInput.value);
    }

    if (combId === 0) {
      const productElement = document.querySelector('[data-id-product]');
      if (productElement) {
        const attrId = productElement.getAttribute('data-id-product-attribute');
        if (attrId) combId = parseInt(attrId);
      }
    }

    if (combId === 0) {
      const url = window.location.href;
      const match = url.match(/\/(\d+)-(\d+)-/);
      if (match && match[2]) combId = parseInt(match[2]);
    }

    return combId;
  }

  getStockElement() {
    const selectors = [
      '.disponible',
      '.stock-dispo',
      '.product-quantity',
      '.quantity',
      '[id^=stock_]',
      '.available-quantity',
      '.product-availability'
    ];

    for (let selector of selectors) {
      const element = document.querySelector(selector);
      if (element) return element;
    }
    return null;
  }

  getCurrentStock() {
    if (!this.stockElement) return null;

    const text = this.stockElement.textContent || this.stockElement.innerText;
    const match = text.match(/(\d+)/);
    return match ? parseInt(match[1]) : null;
  }

  applyStockFormat() {
    if (this.isApplyingFormat || this.hasAppliedFormat) return false;
    this.isApplyingFormat = true;

    // ✅ Asegurarnos de tener el elemento de stock ANTES de continuar
    if (!this.stockElement) {
      this.stockElement = this.getStockElement();
      if (!this.stockElement) {
        console.warn('❌ No se encontró elemento de stock, reintentando...');
        this.isApplyingFormat = false;

        // Reintentar después de un tiempo
        setTimeout(() => {
          if (!this.hasAppliedFormat) {
            this.applyStockFormat();
          }
        }, 500);
        return false;
      }
    }

    const combId = this.getCombinationId();
    const config = this.stockConfig[combId];
    const currentStock = this.getCurrentStock();

    // console.log('📦 Aplicando formato:', {
    //   combId,
    //   currentStock,
    //   config: config ? `${config.min}→${config.display}` : 'none'
    // });

    // GUARDAR EL VALOR ORIGINAL SOLO LA PRIMERA VEZ
    if (!this.originalStockValue && currentStock) {
      this.originalStockValue = currentStock;
      // console.log('📦 Stock original guardado:', this.originalStockValue);
    }

    let applied = false;

    if (config && this.originalStockValue !== null && this.stockElement) {
      const realStock = this.originalStockValue;

      // console.log('📦 Usando stock real:', realStock, 'Config:', config);

      // CALCULAR STOCK A MOSTRAR
      let stockToShow = config.display;
      let showPlusSign = true;

      if (realStock < config.min) {
        const diferencia = config.min - realStock;
        stockToShow = Math.max(0, config.display - diferencia);
        showPlusSign = false;
      }

      // console.log('📦 Stock a mostrar:', stockToShow, 'Mostrar +:', showPlusSign);

      // Aplicar el cambio
      const currentHtml = this.stockElement.innerHTML;
      const regex = /(\d+)\s*uds?/gi;

      // Construir el texto a mostrar
      const displayText = showPlusSign
        ? `<span style="color: #8abd38; font-weight: bold;">+${stockToShow} uds</span>`
        : `<span style="color: #8abd38; font-weight: bold;">${stockToShow} uds</span>`;

      if (!currentHtml.includes(displayText)) {
        const newHtml = currentHtml.replace(regex, displayText);

        if (newHtml !== currentHtml) {
          this.stockElement.innerHTML = newHtml;
          applied = true;
          this.hasAppliedFormat = true;

          // console.log('✅ Formato aplicado correctamente: ' + (showPlusSign ? '+' : '') + stockToShow + ' uds');
          this.showStock();
        }
      } else {
        // console.log('ℹ️ El formato ya estaba aplicado');
        this.hasAppliedFormat = true;
        this.showStock();
      }
    } else {
      console.warn('❌ No se pudo aplicar formato:', {
        hasConfig: !!config,
        hasOriginalStock: this.originalStockValue !== null,
        hasElement: !!this.stockElement
      });

      // Fallback: mostrar después de 2 segundos aunque falle
      setTimeout(() => {
        if (!this.hasAppliedFormat) {
          // console.log('🕒 Fallback: Mostrando stock sin cambios');
          this.hasAppliedFormat = true;
          this.showStock();
        }
      }, 2000);
    }

    this.isApplyingFormat = false;
    return applied;
  }


  initializeObserver() {
    if (!this.stockElement) {
      setTimeout(() => this.initializeObserver(), 1000);
      return;
    }

    if (this.observer) {
      this.observer.disconnect();
    }

    this.observer = new MutationObserver((mutations) => {
      let shouldCheck = false;

      mutations.forEach((mutation) => {
        if (mutation.type === 'childList' || mutation.type === 'characterData') {
          const target = mutation.target;
          if (target === this.stockElement || this.stockElement.contains(target)) {
            const text = target.textContent || target.innerText;
            if (text && text.match(/\d+\s*uds?/) && !text.includes('+')) {
              shouldCheck = true;
              // console.log('📦 Observer: Stock cambiado, reiniciando...');
              this.hasAppliedFormat = false;
              this.originalStockValue = null;
            }
          }
        }
      });

      if (shouldCheck && !this.isApplyingFormat) {
        setTimeout(() => this.applyStockFormat(), 100);
      }
    });

    this.observer.observe(this.stockElement, {
      childList: true,
      subtree: true,
      characterData: true
    });
  }

  interceptPrestaShopAjax() {
    // Interceptar jQuery AJAX de PrestaShop
    if (typeof jQuery !== 'undefined') {
      $(document).ajaxSuccess((event, xhr, settings) => {
        if (settings.url && settings.url.includes('controller=product') &&
          settings.data && settings.data.includes('action=refresh')) {
          // console.log('📦 AJAX de producto detectado, reinicializando...');

          // Esperar a que el DOM se actualice completamente
          setTimeout(() => {
            this.reinitializeAfterAjax();
          }, 300);
        }
      });
    }

    // Interceptar Fetch API
    const originalFetch = window.fetch;
    window.fetch = (...args) => {
      const result = originalFetch.apply(this, args);
      const url = args[0];

      if (typeof url === 'string' &&
        url.includes('controller=product') &&
        url.includes('action=refresh')) {
        result.then(() => {
          setTimeout(() => {
            this.reinitializeAfterAjax();
          }, 300);
        });
      }
      return result;
    };

    // NUEVO: Interceptar eventos específicos de PrestaShop
    if (typeof prestashop !== 'undefined') {
      prestashop.on('updatedProduct', () => {
        // console.log('📦 Evento updatedProduct de PrestaShop, reinicializando...');
        setTimeout(() => {
          this.reinitializeAfterAjax();
        }, 400);
      });
    }
  }

  interceptAttributeChanges() {
    document.addEventListener('change', (event) => {
      const target = event.target;

      if ((target.tagName === 'SELECT' && target.name && target.name.startsWith('group')) ||
        (target.tagName === 'INPUT' && target.name === 'idCombination')) {
        // console.log('📦 Atributo cambiado, reiniciando...');
        setTimeout(() => {
          this.hasAppliedFormat = false;
          this.originalStockValue = null;
          this.applyStockFormat();
        }, 800);
      }
    });
  }

  startSafePolling() {
    setInterval(() => {
      const combId = this.getCombinationId();
      if (combId !== this.currentCombinationId) {
        // console.log('📦 Combinación cambiada vía polling:', combId);
        this.currentCombinationId = combId;
        this.hasAppliedFormat = false;
        this.originalStockValue = null;
        this.applyStockFormat();
      }
    }, 2000);
  }
}

// Auto-inicialización
if (typeof window.stockDisplayConfig !== 'undefined') {
  // console.log('📦 Custom Stock: Configuración detectada, inicializando...');

  const initStockManager = () => {
    if (!window.stockDisplayManagerInstance) {
      window.stockDisplayManagerInstance = new StockDisplayManager(window.stockDisplayConfig);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStockManager);
  } else {
    initStockManager();
  }
}