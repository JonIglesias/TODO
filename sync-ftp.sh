#!/bin/bash
# Script para sincronizar archivos vía FTP
# Uso: bash sync-ftp.sh

echo "=========================================="
echo "Sincronización FTP - Archivos KB"
echo "=========================================="
echo ""

# Solicitar credenciales
read -p "FTP Host [ftp.bocetosmarketing.com]: " FTP_HOST
FTP_HOST=${FTP_HOST:-ftp.bocetosmarketing.com}

read -p "FTP User: " FTP_USER
read -sp "FTP Password: " FTP_PASS
echo ""
echo ""

# Archivos a subir (rutas relativas desde este directorio)
FILES_BOT=(
    "BOT/kb/kb.php:wp-content/plugins/BOT/kb/kb.php"
    "BOT/kb/kb-core.php:wp-content/plugins/BOT/kb/kb-core.php"
)

FILES_API=(
    "api_claude_5/index.php:api_claude_5/index.php"
    "api_claude_5/.htaccess:api_claude_5/.htaccess"
    "api_claude_5/bot/endpoints/generate-kb.php:api_claude_5/bot/endpoints/generate-kb.php"
    "api_claude_5/bot/endpoints/list-models.php:api_claude_5/bot/endpoints/list-models.php"
)

echo "Archivos a actualizar:"
echo "  BOT:"
echo "    - kb/kb.php"
echo "    - kb/kb-core.php"
echo "  API:"
echo "    - index.php"
echo "    - .htaccess"
echo "    - bot/endpoints/generate-kb.php"
echo "    - bot/endpoints/list-models.php"
echo ""

read -p "¿Continuar con la subida? (y/n): " confirm
if [ "$confirm" != "y" ]; then
    echo "Cancelado"
    exit 0
fi

echo ""
echo "Subiendo archivos..."

# Función para subir archivo
upload_file() {
    local_file=$1
    remote_path=$2

    if [ ! -f "$local_file" ]; then
        echo "  ✗ Archivo no encontrado: $local_file"
        return 1
    fi

    echo "  → $local_file"
    lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" <<EOF
cd /public_html
put -O $(dirname "$remote_path") "$local_file" -o $(basename "$remote_path")
quit
EOF

    if [ $? -eq 0 ]; then
        echo "    ✓ Subido"
    else
        echo "    ✗ Error"
    fi
}

# Subir archivos BOT
echo ""
echo "BOT:"
for file_pair in "${FILES_BOT[@]}"; do
    IFS=':' read -r local remote <<< "$file_pair"
    upload_file "$local" "$remote"
done

# Subir archivos API
echo ""
echo "API:"
for file_pair in "${FILES_API[@]}"; do
    IFS=':' read -r local remote <<< "$file_pair"
    upload_file "$local" "$remote"
done

echo ""
echo "=========================================="
echo "✓ Sincronización completada"
echo "=========================================="
echo ""
echo "Prueba la generación de KB desde:"
echo "https://bocetosmarketing.com/wp-admin/admin.php?page=phsbot_kb"
