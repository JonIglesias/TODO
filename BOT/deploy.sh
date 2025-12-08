#!/bin/bash
echo "🚀 Subiendo BOT al servidor..."
lftp << 'FTPEOF'
set ftp:ssl-allow yes
set ssl:verify-certificate no
open -u bocetos@bocetosmarketing.com,##Iqos2020## ftp.bocetosmarketing.com
mirror -R --delete --verbose --exclude .DS_Store --exclude deploy.sh --exclude .git/ --exclude .gitignore /Users/tiburcio/Downloads/GIT/TODO/BOT /public_html/wp-content/plugins/BOT
quit
FTPEOF
echo "✅ BOT actualizado"
