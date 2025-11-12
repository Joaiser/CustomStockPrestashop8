<div class="panel custom-stock-module registered-products-page">
  <div class="panel-heading">
    <i class="icon-list"></i> {$displayName} - Productos con Configuración Personalizada
  </div>

  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation">
      <a href="{$admin_modules_url|escape:'html':'UTF-8'}" class="config-stock-link">
        <i class="icon-edit"></i> Configurar Stock
      </a>
    </li>
    <li role="presentation" class="active">
      <a href="#tab-registered" aria-controls="tab-registered" role="tab" data-toggle="tab">
        <i class="icon-list"></i> Productos Registrados
        <span class="badge" id="registered-count">{$registered_count|default:0}</span>
      </a>
    </li>
    <li role="presentation">
      <a href="{$admin_modules_url|escape:'html':'UTF-8'}#tab-logs" class="logs-link">
        <i class="icon-file-text"></i> Logs
      </a>
    </li>
  </ul>

  <!-- CONTENIDO DE PESTAÑAS -->
  <div class="tab-content">

    <!-- Pestaña 2: Productos Registrados -->
    <div role="tabpanel" class="tab-pane active" id="tab-registered">
      <div class="alert alert-warning">
        <i class="icon-info-circle"></i>
        Esta sección muestra todos los productos que tienen configuración de stock personalizada.
        Puedes editar, eliminar o buscar configuraciones existentes.
      </div>

      <!-- BARRA DE HERRAMIENTAS -->
      <div class="panel panel-default">
        <div class="panel-heading">
          <div class="row">
            <div class="col-md-6">
              <div class="form-inline">
                <div class="form-group">
                  <input type="text" id="registered_search" class="form-control" placeholder="Buscar en registrados..."
                    style="width: 250px;">
                </div>
                <button type="button" id="search_registered" class="btn btn-default">
                  <i class="icon-search"></i> Buscar
                </button>
                <button type="button" id="clear_registered_search" class="btn btn-link">
                  Limpiar
                </button>
              </div>
            </div>
            <div class="col-md-6 text-right">
              <div class="btn-group">
                <button type="button" id="refresh_registered" class="btn btn-default">
                  <i class="icon-refresh"></i> Actualizar
                </button>
                <button type="button" id="bulk_delete" class="btn btn-danger" disabled>
                  <i class="icon-trash"></i> Eliminar seleccionados
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TABLA DE PRODUCTOS REGISTRADOS -->
      <div class="table-responsive">
        <table class="table table-bordered table-hover" id="registered_products_table">
          <thead>
            <tr>
              <th width="30">
                <input type="checkbox" id="select_all_registered">
              </th>
              <th width="80">ID</th>
              <th>Producto</th>
              <th width="120">Tipo</th>
              <th width="150">Referencia</th>
              <th width="120">Stock Mínimo</th>
              <th width="120">Stock Mostrar</th>
              <th width="150">Actualizado</th>
              <th width="100">Acciones</th>
            </tr>
          </thead>
          <tbody id="registered_products_body">
            <tr>
              <td colspan="9" class="text-center">
                <div class="loading-spinner">
                  <i class="icon-spinner icon-spin icon-2x"></i>
                  <p>Cargando productos registrados...</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- PAGINACIÓN REGISTRADOS -->
      <div class="panel-footer">
        <div class="row">
          <div class="col-md-6">
            <div id="registered_results_counter">
              Cargando...
            </div>
          </div>
          <div class="col-md-6 text-right">
            <div id="registered_pagination"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<script type="text/javascript">
  // Variables globales necesarias para el JS
  window.customStockAdminUrl = '{$custom_stock_admin_url nofilter}';
  window.customStockAdminToken = '{$custom_stock_admin_token nofilter}';
  window.adminModulesUrl = '{$admin_modules_url nofilter}';
</script>

<script type="text/javascript" src="{$module_dir}views/js/registered-products/registered-products.js"></script>