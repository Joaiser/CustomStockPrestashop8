<div class="panel custom-stock-module">
  <div class="panel-heading">
    <i class="icon-cogs"></i> {$displayName} - Configuración de Stock Personalizado
  </div>

  <!-- PESTAÑAS -->
  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active">
      <a href="#tab-config" aria-controls="tab-config" role="tab" data-toggle="tab">
        <i class="icon-edit"></i> Configurar Stock
      </a>
    <li role="presentation">
      <a href="{$admin_custom_stock_url|escape:'html':'UTF-8'}" class="registered-products-link">
        <i class="icon-list"></i> Productos Registrados
        <span class="badge" id="registered-count">{$registered_count|default:0}</span>
      </a>
    </li>
    <li role="presentation">
      <a href="#tab-logs" aria-controls="tab-logs" role="tab" data-toggle="tab">
        <i class="icon-file-text"></i> Logs
      </a>
    </li>
  </ul>

  <!-- CONTENIDO DE PESTAÑAS -->
  <div class="tab-content">

    <!-- Pestaña 1: Configurar Stock -->
    <div role="tabpanel" class="tab-pane active" id="tab-config">
      <div class="alert alert-info">
        <p><strong>Instrucciones:</strong></p>
        <ul>
          <li><strong>Stock Mínimo:</strong> Cuando el stock real sea igual o superior a este valor, se mostrará el
            "Stock a Mostrar"</li>
          <li><strong>Stock a Mostrar:</strong> Valor que se mostrará cuando el stock real supere el mínimo (ej: +100)
          </li>
          <li>Si el stock real es inferior al mínimo, se mostrará el stock real</li>
        </ul>
      </div>

      <!-- BUSCADOR -->
      <div class="panel panel-default">
        <div class="panel-heading">
          <i class="icon-search"></i> Buscar Productos
        </div>
        <div class="panel-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="product_search">Buscar por nombre o referencia:</label>
                <input type="text" id="product_search" class="form-control" placeholder="Escribe para buscar...">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="rows_per_page">Productos por página:</label>
                <select id="rows_per_page" class="form-control">
                  <option value="20">20</option>
                  <option value="50" selected>50</option>
                  <option value="100">100</option>
                  <option value="200">200</option>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>&nbsp;</label>
                <button type="button" id="clear_search" class="btn btn-default btn-block">
                  <i class="icon-eraser"></i> Limpiar búsqueda
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <form method="post" action="{$module_url}" id="stock_config_form">
        <input type="hidden" name="token" value="{$token}">

        <div class="table-responsive">
          <table class="table table-bordered table-hover" id="products_table">
            <thead>
              <tr>
                <th width="80">ID</th>
                <th>Producto</th>
                <th width="150">Referencia</th>
                <th width="150">Stock Mínimo</th>
                <th width="150">Stock a Mostrar</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$products item=product}
              <tr data-product-id="{$product.id_product}" data-product-name="{$product.name|lower}"
                data-product-reference="{$product.reference|lower}">
                <td>{$product.id_product}</td>
                <td class="product-name">{$product.name}</td>
                <td class="product-reference">{$product.reference}</td>
                <td>
                  <input type="number" name="stock_min[{$product.id_product}]" value="{$product.stock_min}" min="0"
                    class="form-control stock-input" style="width: 100%;">
                </td>
                <td>
                  <input type="number" name="stock_display[{$product.id_product}]" value="{$product.stock_display}"
                    min="0" class="form-control stock-input" style="width: 100%;">
                </td>
              </tr>
              {foreachelse}
              <tr>
                <td colspan="5" class="text-center">No se encontraron productos activos</td>
              </tr>
              {/foreach}
            </tbody>
          </table>
        </div>

        <!-- CONTADOR DE RESULTADOS -->
        <div class="well well-sm" id="results_counter">
          Mostrando <span id="visible_count">{count($products)}</span> de <span
            id="total_count">{count($products)}</span>
          productos
        </div>

        <!-- PAGINACIÓN DINÁMICA -->
        <div class="panel-footer" id="pagination_container">
          {if $total_pages > 1}
          <div class="row">
            <div class="col-md-6">
              <span>Página {$current_page} de {$total_pages}</span>
            </div>
            <div class="col-md-6 text-right">
              {if $current_page > 1}
              <a href="{$module_url}&page={$current_page - 1}" class="btn btn-default">
                <i class="icon-chevron-left"></i> Anterior
              </a>
              {/if}

              {* Paginación numérica *}
              {for $p=1 to $total_pages}
              {if $p == $current_page}
              <span class="btn btn-primary">{$p}</span>
              {elseif $p >= $current_page - 2 && $p <= $current_page + 2} <a href="{$module_url}&page={$p}"
                class="btn btn-default">{$p}</a>
                {/if}
                {/for}

                {if $current_page < $total_pages} <a href="{$module_url}&page={$current_page + 1}"
                  class="btn btn-default">
                  Siguiente <i class="icon-chevron-right"></i>
                  </a>
                  {/if}
            </div>
          </div>
          {/if}
        </div>

        <div class="panel-footer">
          <button type="submit" name="submit_customstockdisplay" class="btn btn-primary">
            <i class="icon-save"></i> Guardar Configuración
          </button>
          <button type="button" id="select_all" class="btn btn-default">
            <i class="icon-check-square"></i> Seleccionar todos los visibles
          </button>
          <button type="button" id="deselect_all" class="btn btn-default">
            <i class="icon-square"></i> Deseleccionar todos
          </button>
        </div>
      </form>
    </div>

    <!-- Pestaña 3: Logs -->
    <div role="tabpanel" class="tab-pane" id="tab-logs">
      {$log_content nofilter}
    </div>
  </div>
</div>