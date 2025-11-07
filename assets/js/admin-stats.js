/**
 * JavaScript para el panel de estadísticas
 */

jQuery(document).ready(function($) {
    console.log('📊 CV Stats: Panel cargado');
    
    // Auto-refresh cada 5 minutos (solo en dashboard)
    if ($('#cv_stats_logins_today').length > 0) {
        setInterval(function() {
            location.reload();
        }, 5 * 60 * 1000);
    }
});

