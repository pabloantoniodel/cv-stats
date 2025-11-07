<?php
/**
 * Card de Referencias desde Buscadores
 * Vista para mostrar estadísticas de visitas desde motores de búsqueda
 * 
 * @package CV_Stats
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener instancia del tracker
$tracker = new CV_Search_Referral_Tracker();

// Usar las mismas fechas que el resto de la página
// $period_start y $period_end ya están definidas en stats-page.php
$total_visits = $tracker->get_total_referrals_by_date_range($period_start, $period_end);
$stats_by_engine = $tracker->get_stats_by_search_engine_date_range($period_start, $period_end);
$top_terms = $tracker->get_top_search_terms_date_range($period_start, $period_end, 10);
$recent_visits = $tracker->get_recent_referrals_date_range($period_start, $period_end, 10);
?>

<!-- Referencias desde Buscadores -->
<div class="cv-stats-card">
    <h2>🔍 Referencias desde Buscadores</h2>
    <p style="margin: -10px 0 20px 0; color: #666; font-size: 14px;"><?php echo $period_label; ?></p>
    
    <div class="cv-search-referrals-content">
        <!-- Total de visitas -->
        <div class="cv-stat-total">
            <div class="cv-stat-number"><?php echo number_format_i18n($total_visits); ?></div>
            <div class="cv-stat-label"><?php _e('Visitas desde buscadores', 'cv-stats'); ?></div>
        </div>
        
        <!-- Estadísticas por buscador -->
        <?php if (!empty($stats_by_engine)): ?>
        <div class="cv-stat-section">
            <h4><?php _e('Por motor de búsqueda', 'cv-stats'); ?></h4>
            <div class="cv-search-engines-list">
                <?php foreach ($stats_by_engine as $engine): ?>
                <div class="cv-search-engine-item">
                    <div class="cv-engine-info">
                        <span class="cv-engine-icon">
                            <?php
                            $icons = array(
                                'Google' => '🔍',
                                'Bing' => '🅱️',
                                'Yahoo' => 'Y!',
                                'DuckDuckGo' => '🦆',
                                'Yandex' => 'Я',
                                'Baidu' => '百',
                            );
                            echo isset($icons[$engine->search_engine]) ? $icons[$engine->search_engine] : '🔎';
                            ?>
                        </span>
                        <span class="cv-engine-name"><?php echo esc_html($engine->search_engine); ?></span>
                    </div>
                    <div class="cv-engine-stats">
                        <span class="cv-engine-visits"><?php echo number_format_i18n($engine->total_visits); ?></span>
                        <span class="cv-engine-terms-label"><?php printf(__('%d términos únicos', 'cv-stats'), $engine->unique_terms); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Top términos de búsqueda -->
        <?php if (!empty($top_terms)): ?>
        <div class="cv-stat-section">
            <h4><?php _e('Búsquedas más frecuentes', 'cv-stats'); ?></h4>
            <div class="cv-search-terms-list">
                <?php foreach ($top_terms as $term): ?>
                <div class="cv-search-term-item">
                    <div class="cv-term-text">
                        <span class="cv-term-icon">💬</span>
                        <strong>"<?php echo esc_html($term->search_terms); ?>"</strong>
                        <span class="cv-term-engine">(<?php echo esc_html($term->search_engine); ?>)</span>
                    </div>
                    <div class="cv-term-stats">
                        <span class="cv-term-visits"><?php echo number_format_i18n($term->visits); ?> visitas</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas por país -->
        <?php 
        $stats_by_country = $tracker->get_stats_by_country_date_range($period_start, $period_end);
        if (!empty($stats_by_country)): 
        ?>
        <div class="cv-stat-section">
            <h4><?php _e('Por país de origen', 'cv-stats'); ?></h4>
            <div class="cv-countries-list">
                <?php foreach ($stats_by_country as $country): ?>
                <div class="cv-country-item">
                    <div class="cv-country-info">
                        <span class="cv-country-flag">🌍</span>
                        <span class="cv-country-code"><?php echo esc_html($country->country_code); ?></span>
                    </div>
                    <div class="cv-country-visits">
                        <span class="cv-country-count"><?php echo number_format_i18n($country->total_visits); ?></span>
                        <span class="cv-country-label">visitas</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Visitas recientes -->
        <?php if (!empty($recent_visits)): ?>
        <div class="cv-stat-section">
            <h4><?php _e('Visitas recientes', 'cv-stats'); ?></h4>
            <div class="cv-recent-visits-list">
                <?php foreach ($recent_visits as $visit): ?>
                <div class="cv-recent-visit-item">
                    <div class="cv-visit-time">
                        <?php echo human_time_diff(strtotime($visit->created_at), current_time('timestamp')); ?> <?php _e('ago', 'cv-stats'); ?>
                    </div>
                    <div class="cv-visit-info">
                        <strong><?php echo esc_html($visit->search_engine); ?></strong>
                        <?php if ($visit->search_terms): ?>
                            → "<?php echo esc_html(substr($visit->search_terms, 0, 50)); ?><?php echo strlen($visit->search_terms) > 50 ? '...' : ''; ?>"
                        <?php endif; ?>
                        <span class="cv-visit-country">(<?php echo esc_html($visit->country_code); ?>)</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (empty($stats_by_engine) && empty($top_terms)): ?>
        <div class="cv-stats-empty-state">
            <p>🔍 No hay datos de búsquedas todavía. Los datos comenzarán a aparecer cuando los visitantes lleguen desde motores de búsqueda.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>

.cv-stat-total {
    text-align: center;
    padding: 30px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
}

.cv-stat-number {
    font-size: 56px;
    font-weight: 700;
    margin-bottom: 8px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.cv-stat-label {
    font-size: 18px;
    opacity: 0.95;
}

.cv-stat-section {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.cv-stat-section h4 {
    margin: 0 0 15px 0;
    font-size: 17px;
    font-weight: 600;
    color: #1f2937;
    border-bottom: 2px solid #667eea;
    padding-bottom: 10px;
}

.cv-search-engine-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: all 0.2s;
}

.cv-search-engine-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-color: #667eea;
}

.cv-engine-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.cv-engine-icon {
    font-size: 24px;
}

.cv-engine-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 15px;
}

.cv-engine-stats {
    text-align: right;
}

.cv-engine-visits {
    font-size: 28px;
    font-weight: 700;
    display: block;
    color: #667eea;
}

.cv-engine-terms-label {
    font-size: 12px;
    color: #6b7280;
}

.cv-search-term-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 8px;
}

.cv-search-term-item:hover {
    background: #f9fafb;
}

.cv-term-text {
    flex: 1;
}

.cv-term-icon {
    margin-right: 8px;
    font-size: 16px;
}

.cv-term-text strong {
    color: #1f2937;
}

.cv-term-engine {
    font-size: 12px;
    color: #6b7280;
    margin-left: 8px;
}

.cv-term-visits {
    font-weight: 600;
    white-space: nowrap;
    color: #667eea;
    font-size: 15px;
}

.cv-recent-visit-item {
    padding: 12px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 8px;
}

.cv-visit-time {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 6px;
}

.cv-visit-info {
    font-size: 14px;
    color: #1f2937;
}

.cv-visit-country {
    font-size: 12px;
    color: #6b7280;
    background: #f3f4f6;
    padding: 2px 8px;
    border-radius: 4px;
    margin-left: 8px;
}

.cv-country-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 8px;
}

.cv-country-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cv-country-flag {
    font-size: 20px;
}

.cv-country-code {
    font-weight: 600;
    color: #1f2937;
    font-size: 14px;
}

.cv-country-visits {
    text-align: right;
}

.cv-country-count {
    font-size: 24px;
    font-weight: 700;
    color: #667eea;
    display: block;
}

.cv-country-label {
    font-size: 12px;
    color: #6b7280;
}
</style>

