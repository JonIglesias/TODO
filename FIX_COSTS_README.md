# Recalcular Costes Históricos

Los registros históricos de `api_usage_tracking` contienen costes calculados con precios incorrectos debido al bug de detección de modelos (gpt-4.1 detectado como gpt-4).

## ⚠️ IMPORTANTE: Hacer BACKUP

```bash
# Backup de la tabla completa
mysqldump -u usuario -p basedatos api_usage_tracking > backup_usage_tracking_$(date +%Y%m%d).sql
```

## Opción 1: Script PHP (Recomendado)

El script PHP usa `ModelPricingService` para obtener precios correctos y es más seguro.

```bash
php fix_historical_costs.php
```

### Ventajas:
- ✅ Usa la misma lógica de precios que la API
- ✅ Pide confirmación antes de ejecutar
- ✅ Muestra detalles de cada corrección
- ✅ Maneja errores de forma segura
- ✅ Calcula el total de corrección

### Salida esperada:
```
=== RECALCULAR COSTES HISTÓRICOS ===

Registros encontrados: 156
¿Deseas continuar? (yes/no): yes

Procesando...

ID 3696 - Modelo: gpt-4.1-2025-04-14
  Tokens: 1265i / 148o
  Precio usado: $2/$8 por millón
  Coste anterior: $0.04683
  Coste correcto: $0.00371
  Diferencia: $0.04312

[...]

===================================
RESUMEN:
  Registros procesados: 156
  Registros actualizados: 47
  Errores: 0
  Corrección total: $0.23456

✅ Se corrigió un sobrecargo de $0.23456
```

## Opción 2: SQL directo

Si prefieres ejecutar SQL directamente en tu cliente MySQL/phpMyAdmin:

### Paso 1: Ver qué se actualizaría (SIN cambios)

```sql
-- Copia y ejecuta las líneas 14-54 de fix_historical_costs.sql
-- Esto SOLO muestra los registros, NO los modifica
```

Verás algo como:
```
| id   | campaign_id | model              | costo_actual | costo_correcto | diferencia |
|------|-------------|-----------------------|--------------|----------------|------------|
| 3696 | 14          | gpt-4.1-2025-04-14 | 0.04683      | 0.00371        | 0.04312    |
| 3697 | 14          | gpt-4.1-2025-04-14 | 0.03795      | 0.00253        | 0.03542    |
```

### Paso 2: Ver resumen por campaña

```sql
-- Copia y ejecuta las líneas 107-129 de fix_historical_costs.sql
```

Verás:
```
| campaign_id | operaciones | tokens_totales | costo_actual | costo_correcto | sobrecargo |
|-------------|-------------|----------------|--------------|----------------|------------|
| 14          | 47          | 125847         | 3.7754       | 0.2518         | 3.5236     |
```

### Paso 3: Ejecutar UPDATE (MODIFICA datos)

```sql
-- Descomentar las líneas 60-91 de fix_historical_costs.sql
-- ⚠️ ESTO MODIFICA LOS DATOS - Asegúrate de tener backup
```

## Verificación después del UPDATE

Ejecuta el script de verificación:

```bash
php verify_agosto.php
```

Debería mostrar:
```
✅ COSTES CORRECTOS
El modelo 'gpt-4.1-2025-04-14' ahora usa precios correctos ($2/$8)
```

## ¿Qué registros se afectan?

Principalmente registros con:
- **gpt-4.1** detectado como gpt-4 (sobrecargado 15x)
- **claude-3-5-haiku** o **claude-3-5-sonnet** detectados como versiones más cortas

## Impacto en AGOSTO

Campaña AGOSTO (campaign_id='14'):
- **Coste registrado**: ~$3.77
- **Coste correcto**: ~$0.25
- **Sobrecargo**: ~$3.52

## Preguntas Frecuentes

### ¿Debo actualizar los datos históricos?

Depende:
- **SÍ** si necesitas estadísticas precisas o créditos/facturación correctos
- **NO** si solo quieres que los nuevos registros sean correctos (el fix ya está aplicado)

### ¿Afecta a nuevos registros?

**NO**. El fix en `ModelPricingService.php` ya corrige el problema para todas las operaciones futuras.

### ¿Puedo revertir si algo sale mal?

**SÍ**, si hiciste backup. Restaura con:

```bash
mysql -u usuario -p basedatos < backup_usage_tracking_20241210.sql
```

## Archivos relacionados

- `fix_historical_costs.php` - Script PHP para recalcular (recomendado)
- `fix_historical_costs.sql` - SQL directo para ejecutar en cliente MySQL
- `verify_agosto.php` - Verificar costes campaña AGOSTO
- `test_model_pricing_fix.php` - Tests del fix aplicado
