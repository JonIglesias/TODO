/**
 * Sistema de Heartbeat para Bloqueos V2
 * 
 * Renueva automáticamente los bloqueos mientras hay procesos activos
 * 
 * @version 2.0
 */

(function($) {
    'use strict';
    
    const LockHeartbeat = {
        
        interval: null,
        active: false,
        renewInterval: 20000, // 20 segundos
        
        /**
         * Iniciar heartbeat
         */
        start: function(type, campaignId) {
            if (this.active) {
                console.log('⚠️ Heartbeat ya está activo');
                return;
            }
            
            this.active = true;
            
            console.log('💓 Heartbeat iniciado', {
                type: type,
                campaign_id: campaignId,
                interval: this.renewInterval + 'ms'
            });
            
            // Renovar cada 20 segundos
            this.interval = setInterval(() => {
                this.renew(type, campaignId);
            }, this.renewInterval);
        },
        
        /**
         * Detener heartbeat
         */
        stop: function() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
                this.active = false;
                console.log('💔 Heartbeat detenido');
            }
        },
        
        /**
         * Renovar bloqueo
         */
        renew: function(type, campaignId) {
            $.post(apQueue.ajax_url, {
                action: 'ap_renew_lock',
                nonce: apQueue.nonce,
                type: type,
                campaign_id: campaignId
            }, (response) => {
                if (response.success) {
                    console.log('💚 Heartbeat renovado', {
                        type: type,
                        campaign_id: campaignId
                    });
                } else {
                    console.error('❌ Error renovando heartbeat:', response.data);
                    // Si falla, detener heartbeat
                    this.stop();
                }
            }).fail(() => {
                console.error('❌ Fallo de conexión en heartbeat');
            });
        }
    };
    
    // Exportar globalmente
    window.LockHeartbeat = LockHeartbeat;
    
    // Auto-detener al salir de la página
    $(window).on('beforeunload', function() {
        if (LockHeartbeat.active) {
            LockHeartbeat.stop();
        }
    });
    
})(jQuery);
