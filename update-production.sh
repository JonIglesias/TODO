#!/bin/bash
# Script para actualizar plugins y API en producción
# Ejecutar: bash update-production.sh

set -e  # Exit on error

echo "=========================================="
echo "Actualización de Producción"
echo "=========================================="
echo ""

# Directorio base de WordPress
WP_DIR="/home/bocetosm/public_html"

# 1. Actualizar plugin BOT
echo "1. Actualizando plugin BOT..."
if [ -d "$WP_DIR/wp-content/plugins/BOT/.git" ]; then
    cd "$WP_DIR/wp-content/plugins/BOT"
    git fetch origin
    git checkout claude/analyze-plugins-api-01BAECWTX2MWjBq9vo9FcYi3
    git pull origin claude/analyze-plugins-api-01BAECWTX2MWjBq9vo9FcYi3
    echo "   ✓ BOT actualizado"
else
    echo "   ✗ BOT no es un repositorio Git (copiar archivos manualmente)"
fi
echo ""

# 2. Actualizar plugin GEOWriter
echo "2. Actualizando plugin GEOWriter..."
if [ -d "$WP_DIR/wp-content/plugins/GEOWriter/.git" ]; then
    cd "$WP_DIR/wp-content/plugins/GEOWriter"
    git fetch origin
    git checkout claude/analyze-plugins-api-01BAECWTX2MWjBq9vo9FcYi3
    git pull origin claude/analyze-plugins-api-01BAECWTX2MWjBq9vo9FcYi3
    echo "   ✓ GEOWriter actualizado"
else
    echo "   ✗ GEOWriter no es un repositorio Git (copiar archivos manualmente)"
fi
echo ""

# 3. Actualizar API
echo "3. Actualizando api_claude_5..."
if [ -d "$WP_DIR/api_claude_5/.git" ]; then
    cd "$WP_DIR/api_claude_5"
    git fetch origin
    git checkout claude/analyze-plugins-api-01BAECWTX2MWjBq9vo9FcYi3
    git pull origin claude/analyze-plugins-api-01BAECWTX2MWjBq9vo9FcYi3
    echo "   ✓ API actualizada"
else
    echo "   ✗ api_claude_5 no es un repositorio Git (copiar archivos manualmente)"
fi
echo ""

# 4. Verificar archivos críticos
echo "4. Verificando archivos críticos..."
echo "   - BOT/kb/kb.php: $([ -f "$WP_DIR/wp-content/plugins/BOT/kb/kb.php" ] && echo '✓' || echo '✗')"
echo "   - BOT/kb/kb-core.php: $([ -f "$WP_DIR/wp-content/plugins/BOT/kb/kb-core.php" ] && echo '✓' || echo '✗')"
echo "   - API/bot/endpoints/generate-kb.php: $([ -f "$WP_DIR/api_claude_5/bot/endpoints/generate-kb.php" ] && echo '✓' || echo '✗')"
echo "   - API/index.php: $([ -f "$WP_DIR/api_claude_5/index.php" ] && echo '✓' || echo '✗')"
echo ""

# 5. Verificar git status
echo "5. Estado de Git:"
echo "   BOT:"
cd "$WP_DIR/wp-content/plugins/BOT"
git log --oneline -1
echo ""
echo "   API:"
cd "$WP_DIR/api_claude_5"
git log --oneline -1
echo ""

echo "=========================================="
echo "✓ Actualización completada"
echo "=========================================="
echo ""
echo "Prueba la generación de KB desde:"
echo "https://bocetosmarketing.com/wp-admin/admin.php?page=phsbot_kb"
