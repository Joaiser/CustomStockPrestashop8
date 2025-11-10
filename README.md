# Custom Stock Display for PrestaShop 8

## 📖 Descripción
Módulo para PrestaShop que permite mostrar un stock personalizado en lugar del stock real. Configura cantidades mínimas y valores de display específicos para cada producto.

## 🚀 Características
- ✅ Configuración de stock mínimo y stock a mostrar por producto
- ✅ Soporte para productos simples y combinaciones (atributos)
- ✅ Interfaz administrativa intuitiva con pestañas
- ✅ Búsqueda y filtrado de productos en tiempo real
- ✅ Gestión masiva de configuraciones
- ✅ Sistema de logs integrado

## ⚙️ Instalación
1. Subir la carpeta `customstockdisplay` al directorio `/modules/` de tu PrestaShop
2. Ir al panel de administración → Módulos → Administración de módulos
3. Buscar "Stock Personalizado" y hacer clic en Instalar
4. El módulo estará listo para usar

## 🎯 Uso

### Configurar Stock Personalizado
1. Ir a **Catálogo → Stock Personalizado** (o desde Módulos → Configurar)
2. En la pestaña "Configurar Stock", buscar el producto deseado
3. Establecer:
   - **Stock Mínimo**: Cuando el stock real sea igual o superior, se mostrará el "Stock a Mostrar"
   - **Stock a Mostrar**: Valor que se mostrará cuando se cumpla la condición mínima (ej: +100)

### Ver Productos Configurados
1. En la misma página del módulo, ir a la pestaña "Productos Registrados"
2. Ver todos los productos con configuración activa
3. Editar o eliminar configuraciones individuales o masivas

## 🔧 Funcionamiento Técnico

### Lógica de Display
- **Si stock real ≥ Stock Mínimo** → Muestra "+Stock a Mostrar" (ej: +100 uds)
- **Si stock real < Stock Mínimo** → Muestra el stock real

### Hooks Utilizados
- `displayProductAdditionalInfo` - Inyecta configuración en página de producto
- `displayHeader` - Carga CSS/JS en páginas de producto
- `displayBackOfficeHeader` - Carga assets en admin
- `displayFooterProduct` - Soporte adicional para temas

## 🏗️ Estructura del Módulo
customstockdisplay/
├── customstockdisplay.php # Clase principal del módulo
├── AdminCustomStockDisplayController.php # Controlador admin
├── views/
│ ├── css/
│ │ ├── customstockdisplay.css # Estilos frontend
│ │ └── registered-products.css # Estilos admin
│ ├── js/
│ │ ├── admin.js # JS administración
│ │ ├── stock-display-front.js # JS frontend
│ │ └── registered-products/ # JS específico
│ └── templates/
│ ├── admin/
│ │ ├── config.tpl # Template configuración
│ │ ├── registered_products.tpl # Template productos registrados
│ │ └── logs.tpl # Template logs
│ └── front/ # Templates frontend (futuro)
├── logs/
│ └── customstockdisplay.log # Archivo de logs
└── ...

text

## 🗃️ Base de Datos
Crea la tabla: `ps_customstockdisplay`
```sql
id_customstock | id_product | id_product_attribute | id_shop | stock_min | stock_display | date_add | date_upd
🐛 Solución de Problemas
Problemas Comunes
Stock no se muestra personalizado: Verificar que el producto tenga configuración en la BD

Error en consola JavaScript: Revisar que window.stockDisplayConfig esté definido

Estilos no aplicados: Verificar permisos de archivos CSS

Debugging
Revisar archivo de logs: /modules/customstockdisplay/logs/customstockdisplay.log

Verificar consola del navegador para errores JavaScript

Comprobar que los hooks estén registrados correctamente

📈 Estado Actual
✅ FUNCIONAL EN TESTING

Pendiente para Producción:
Optimizar rendimiento (evitar dobles ejecuciones JS)

Completar documentación técnica

Mejorar estilos del controlador administrativo

Implementar override del template de stock nativo

Eliminar logs de debug para producción

Mejoras Detectadas:
Optimizar llamadas AJAX para evitar race conditions

Implementar cache específico para configuraciones

Soporte multi-tienda completo

🔮 Roadmap Futuro
Override del template de stock nativo de PrestaShop

Sistema de reglas más avanzado (por categoría, marca, etc.)

Panel de estadísticas de uso

Export/import de configuraciones
