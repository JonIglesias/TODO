const API_HELP_CONTENT = {
    unsplash: {
        title: '🖼️ Cómo obtener tu Unsplash API Key',
        content: `
            <ol>
                <li><strong>Regístrate en Unsplash:</strong><br>
                Ve a <a href="https://unsplash.com/join" target="_blank">unsplash.com/join</a> y crea una cuenta gratuita.</li>
                
                <li><strong>Accede a Developers:</strong><br>
                Una vez registrado, ve a <a href="https://unsplash.com/developers" target="_blank">unsplash.com/developers</a></li>
                
                <li><strong>Crea una nueva aplicación:</strong><br>
                Haz clic en "Register as a developer" si es tu primera vez, luego en "New Application".</li>
                
                <li><strong>Acepta los términos:</strong><br>
                Lee y acepta los términos de uso de la API.</li>
                
                <li><strong>Completa el formulario:</strong><br>
                - Application name: "AutoPost IA"<br>
                - Description: "Plugin WordPress para publicación automática"</li>
                
                <li><strong>Copia tu Access Key:</strong><br>
                Después de crear la aplicación, encontrarás tu <strong>Access Key</strong>. Cópiala y pégala en el campo.</li>
            </ol>
            <div style="background: rgba(255,255,255,0.2); padding: 12px; border-radius: 6px; margin-top: 12px;">
                <strong>⚠️ Límites gratuitos:</strong> 50 peticiones/hora<br>
                <strong>✅ Ventajas:</strong> Imágenes de alta calidad, sin watermark
            </div>
        `
    },
    pixabay: {
        title: '🎨 Cómo obtener tu Pixabay API Key',
        content: `
            <ol>
                <li><strong>Crea cuenta en Pixabay:</strong><br>
                Visita <a href="https://pixabay.com/accounts/register/" target="_blank">pixabay.com/accounts/register</a></li>
                
                <li><strong>Verifica tu email:</strong><br>
                Revisa tu correo y confirma tu cuenta.</li>
                
                <li><strong>Accede a la API:</strong><br>
                Ve a <a href="https://pixabay.com/api/docs/" target="_blank">pixabay.com/api/docs</a></li>
                
                <li><strong>Encuentra tu API Key:</strong><br>
                Desplázate hacia abajo. Verás tu API Key en la sección "Search Images".</li>
                
                <li><strong>Copia la clave:</strong><br>
                Copia el código después de "key=". Ejemplo: <code>12345678-abc123def456...</code></li>
                
                <li><strong>Pega en el campo:</strong><br>
                Copia esa clave y pégala en el campo correspondiente.</li>
            </ol>
            <div style="background: rgba(255,255,255,0.2); padding: 12px; border-radius: 6px; margin-top: 12px;">
                <strong>⚠️ Límites gratuitos:</strong> 5000 peticiones/hora<br>
                <strong>✅ Ventajas:</strong> Fácil de obtener, límites generosos
            </div>
        `
    },
    pexels: {
        title: '📸 Cómo obtener tu Pexels API Key',
        content: `
            <ol>
                <li><strong>Regístrate en Pexels:</strong><br>
                Ve a <a href="https://www.pexels.com/join/" target="_blank">pexels.com/join</a> y crea tu cuenta.</li>
                
                <li><strong>Accede al API:</strong><br>
                Visita <a href="https://www.pexels.com/api/" target="_blank">pexels.com/api</a> y haz clic en "Get Started".</li>
                
                <li><strong>Completa el formulario:</strong><br>
                - Describe tu proyecto: "Plugin WordPress de publicación automática"<br>
                - URL: Tu sitio web o deja en blanco</li>
                
                <li><strong>Acepta los términos:</strong><br>
                Lee y acepta las condiciones de uso.</li>
                
                <li><strong>Obtén tu API Key:</strong><br>
                Recibirás tu API Key inmediatamente en pantalla y por email.</li>
                
                <li><strong>Copia y pega:</strong><br>
                Copia tu API Key y pégala en el campo.</li>
            </ol>
            <div style="background: rgba(255,255,255,0.2); padding: 12px; border-radius: 6px; margin-top: 12px;">
                <strong>⚠️ Límites gratuitos:</strong> 200 peticiones/hora<br>
                <strong>✅ Ventajas:</strong> Proceso rápido, calidad profesional
            </div>
        `
    },
    default: {
        title: '💡 Ayuda Rápida',
        content: `
            <div class="ap-help-item">
                <h4>¿Cómo empezar?</h4>
                <p>1. Introduce las API keys de imágenes<br>2. Crea tu primera campaña<br>3. Genera la cola de posts<br>4. Ejecuta y listo!</p>
            </div>
            
            <div class="ap-help-item">
                <h4>API de Generación</h4>
                <p>La URL de la API es donde se envían las peticiones para generar contenido con IA. Verifica tu licencia para activar el servicio.</p>
            </div>
            
            <div class="ap-help-item">
                <h4>Buscadores de Imágenes</h4>
                <p>Necesitas al menos una API key activa. Recomendamos Unsplash por su calidad y facilidad de uso.</p>
            </div>
            
            <div class="ap-help-item">
                <h4>Descripción de Empresa</h4>
                <p>Esta información se usa para personalizar el contenido generado y adaptarlo al tono de tu marca.</p>
            </div>
            
            <div class="ap-help-item">
                <h4>¿Necesitas ayuda?</h4>
                <p>Contacta con soporte en:<br><strong>soporte@bocetosmarketing.com</strong></p>
            </div>
        `
    }
};

function showAPIHelp(provider) {
    const helpCard = document.querySelector('.ap-help-card');
    const data = API_HELP_CONTENT[provider];
    
    if (helpCard) {
        helpCard.innerHTML = '<h3>' + data.title + '</h3>' + data.content;
        helpCard.style.animation = 'slideIn 0.3s ease';
    }
}

function resetHelp() {
    const helpCard = document.querySelector('.ap-help-card');
    const data = API_HELP_CONTENT.default;
    
    if (helpCard) {
        helpCard.innerHTML = '<h3>' + data.title + '</h3>' + data.content;
    }
}
