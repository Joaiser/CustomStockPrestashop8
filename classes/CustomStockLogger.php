<?php

if (!defined('_PS_VERSION_')) {
  exit;
}

class CustomStockLogger
{
  private $module;
  private $logFile;
  private $maxFileSize = 5242880; // 5MB
  private $logLevels = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];

  public function __construct($module)
  {
    $this->module = $module;
    $this->logFile = dirname(__FILE__) . '/../logs/customstockdisplay.log';
    $this->ensureLogDirectoryExists();
  }

  /**
   * Log a message with INFO level
   */
  public function info($message)
  {
    $this->write('INFO', $message);
  }

  /**
   * Log a message with ERROR level
   */
  public function error($message)
  {
    $this->write('ERROR', $message);
  }

  /**
   * Log a message with WARNING level
   */
  public function warning($message)
  {
    $this->write('WARNING', $message);
  }

  /**
   * Log a message with DEBUG level
   */
  public function debug($message)
  {
    $this->write('DEBUG', $message);
  }

  /**
   * Clear the log file
   */
  public function clear()
  {
    if (file_exists($this->logFile)) {
      file_put_contents($this->logFile, '');
      $this->info('Logs limpiados manualmente');
      return true;
    }
    return false;
  }

  /**
   * Get log content
   */
  public function getContent()
  {
    if (file_exists($this->logFile)) {
      $content = file_get_contents($this->logFile);
      return empty($content) ? 'El archivo de logs está vacío' : $content;
    }
    return 'No hay logs disponibles';
  }

  /**
   * Get log file path
   */
  public function getFilePath()
  {
    return $this->logFile;
  }

  /**
   * Check if logging is enabled (útil para debug en producción)
   */
  public function isEnabled()
  {
    return true; // Podría basarse en configuración del módulo
  }

  /**
   * Write log entry
   */
  private function write($level, $message)
  {
    if (!$this->isEnabled()) {
      return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";

    try {
      // Rotar logs si el archivo es muy grande
      $this->rotateIfNeeded();

      file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);

      // También log a PHP error log para debugging en servidor
      if ($level === 'ERROR') {
        error_log("CustomStockDisplay - $level: $message");
      }
    } catch (Exception $e) {
      // Silenciar errores de log para no romper la aplicación
      error_log("CustomStockDisplay - ERROR escribiendo log: " . $e->getMessage());
    }
  }

  /**
   * Rotate log file if it becomes too large
   */
  private function rotateIfNeeded()
  {
    if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
      $backupFile = $this->logFile . '.' . date('Y-m-d_His');
      rename($this->logFile, $backupFile);
      $this->info('Log file rotated: ' . basename($backupFile));
    }
  }

  /**
   * Ensure log directory exists
   */
  private function ensureLogDirectoryExists()
  {
    $logDir = dirname($this->logFile);
    if (!is_dir($logDir)) {
      mkdir($logDir, 0755, true);
    }
  }

  /**
   * Get log statistics
   */
  public function getStats()
  {
    if (!file_exists($this->logFile)) {
      return [
        'file_exists' => false,
        'file_size' => 0,
        'entries_count' => 0
      ];
    }

    $content = file_get_contents($this->logFile);
    $entries = array_filter(explode("\n", $content));

    return [
      'file_exists' => true,
      'file_size' => filesize($this->logFile),
      'entries_count' => count($entries),
      'last_modified' => date('Y-m-d H:i:s', filemtime($this->logFile))
    ];
  }
}
