<div class="panel">
  <div class="panel-heading">
    <i class="icon-file-text"></i> Logs del Módulo
  </div>

  <div class="alert alert-warning">
    <p><strong>Archivo de logs:</strong> {$log_file_path}</p>
    <p>Última actualización: {date('Y-m-d H:i:s')}</p>
  </div>

  <div class="form-group">
    <label for="log_content">Contenido del Log:</label>
    <textarea id="log_content" class="form-control" rows="20" readonly
      style="font-family: monospace; font-size: 12px;">{$log_content}</textarea>
  </div>

  <div class="panel-footer">
    <a href="{$module_url}&refresh_logs=1" class="btn btn-default">
      <i class="icon-refresh"></i> Actualizar Logs
    </a>
    <a href="{$module_url}&clear_logs=1" class="btn btn-danger"
      onclick="return confirm('¿Estás seguro de que quieres limpiar los logs?');">
      <i class="icon-trash"></i> Limpiar Logs
    </a>
  </div>
</div>

<script>
  // Auto-scroll to bottom of logs
  document.addEventListener('DOMContentLoaded', function () {
    var textarea = document.getElementById('log_content');
    if (textarea) {
      textarea.scrollTop = textarea.scrollHeight;
    }
  });
</script>