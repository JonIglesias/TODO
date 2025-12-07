/**
 * AutoPost - Manejador Centralizado de Errores
 *
 * Este archivo centraliza el manejo de errores de API,
 * especialmente los errores de límite de tokens excedido.
 */

(function() {
    'use strict';

    // Crear namespace global si no existe
    window.AutoPost = window.AutoPost || {};

    /**
     * Detecta si un error es de tipo "límite de tokens excedido"
     *
     * @param {Object} response - Respuesta de la API
     * @returns {boolean}
     */
    window.AutoPost.isTokenLimitError = function(response) {
        // Verificar en error_type directo
        if (response && response.error_type === 'token_limit_exceeded') {
            return true;
        }

        // Verificar en data.error_type
        if (response && response.data && response.data.error_type === 'token_limit_exceeded') {
            return true;
        }

        // Verificar en array de error_details
        if (response && response.data && response.data.error_details) {
            var hasTokenError = false;
            response.data.error_details.forEach(function(err) {
                if (err.indexOf('LÍMITE DE TOKENS AGOTADO') !== -1 ||
                    err.indexOf('Token limit exceeded') !== -1) {
                    hasTokenError = true;
                }
            });
            if (hasTokenError) return true;
        }

        // Verificar en mensaje de error directo
        var errorMsg = response?.error || response?.data?.message || '';
        if (errorMsg.indexOf('LÍMITE DE TOKENS AGOTADO') !== -1 ||
            errorMsg.indexOf('Token limit exceeded') !== -1) {
            return true;
        }

        return false;
    };

    /**
     * Extrae el mensaje de error de la respuesta
     *
     * @param {Object} response - Respuesta de la API
     * @returns {string}
     */
    window.AutoPost.extractErrorMessage = function(response) {
        // Intentar obtener el mensaje de varias ubicaciones posibles
        if (response && response.error) {
            return response.error;
        }

        if (response && response.data && response.data.message) {
            return response.data.message;
        }

        if (response && response.data && response.data.error_details) {
            // Buscar mensaje de límite de tokens en error_details
            var tokenMsg = null;
            response.data.error_details.forEach(function(err) {
                if (err.indexOf('LÍMITE DE TOKENS AGOTADO') !== -1) {
                    tokenMsg = err;
                }
            });
            if (tokenMsg) return tokenMsg;

            // Si no es token error, devolver el primer error
            return response.data.error_details[0] || 'Error desconocido';
        }

        return 'Error desconocido';
    };

    /**
     * Muestra un error de límite de tokens en un contenedor específico
     *
     * @param {string} containerId - ID del contenedor donde mostrar el error
     * @param {string} errorMessage - Mensaje de error a mostrar
     */
    window.AutoPost.showTokenLimitError = function(containerId, errorMessage) {
        var container = document.getElementById(containerId);
        if (!container) {
            // Si no hay contenedor, usar alert
            alert('ERROR: ' + errorMessage);
            return;
        }

        var errorHtml = '<div style="background: #ffffff; border: 2px solid #dc2626; padding: 20px; border-radius: 4px; margin: 16px 0;">' +
            '<div style="background: #dc2626; color: #ffffff; font-weight: 600; font-size: 14px; padding: 8px 12px; margin: -20px -20px 16px -20px; border-radius: 2px 2px 0 0;">ERROR</div>' +
            '<div style="color: #000000; white-space: pre-line; line-height: 1.6; font-size: 13px;">' + errorMessage + '</div>' +
            '</div>';

        container.innerHTML = errorHtml;
    };

    /**
     * Muestra un error de límite de tokens como alert
     *
     * @param {string} errorMessage - Mensaje de error a mostrar
     */
    window.AutoPost.alertTokenLimitError = function(errorMessage) {
        alert('ERROR: ' + errorMessage);
    };

    /**
     * Maneja automáticamente un error de API
     * Detecta si es error de tokens y lo muestra apropiadamente
     *
     * @param {Object} response - Respuesta de la API
     * @param {string|null} containerId - ID del contenedor (opcional, si no se proporciona usa alert)
     * @returns {boolean} - true si se manejó el error, false si no
     */
    window.AutoPost.handleApiError = function(response, containerId) {
        var errorMessage = this.extractErrorMessage(response);

        if (this.isTokenLimitError(response)) {
            // Es error de límite de tokens
            if (containerId) {
                this.showTokenLimitError(containerId, errorMessage);
            } else {
                this.alertTokenLimitError(errorMessage);
            }
            return true;
        } else {
            // Error genérico
            if (containerId) {
                var container = document.getElementById(containerId);
                if (container) {
                    container.innerHTML = '<div style="background: #ffffff; border: 2px solid #dc2626; padding: 20px; border-radius: 4px; margin: 16px 0;">' +
                        '<div style="background: #dc2626; color: #ffffff; font-weight: 600; font-size: 14px; padding: 8px 12px; margin: -20px -20px 16px -20px; border-radius: 2px 2px 0 0;">ERROR</div>' +
                        '<div style="color: #000000; white-space: pre-line; line-height: 1.6; font-size: 13px;">' + errorMessage + '</div>' +
                        '</div>';
                } else {
                    alert('ERROR: ' + errorMessage);
                }
            } else {
                alert('ERROR: ' + errorMessage);
            }
            return true;
        }
    };

})();
