<?php
/**
 * Classe completa per il debug e logging delle operazioni Dropbox
 * Funzionalità: logging avanzato, statistiche, health check, esportazione debug
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Dropbox_Debug {
    
    private static $instance = null;
    private static $logs_cache = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook per pulire automaticamente i log vecchi
        add_action('wp_scheduled_delete', array(__CLASS__, 'cleanup_old_logs'));
        
        // Hook per salvare log critici su file separato
        add_action('shutdown', array(__CLASS__, 'save_critical_logs'));
    }
    
    /**
     * Salva log nel database e su file
     */
    public static function debug_log($message, $data = null) {
        // Log sempre attivo per operazioni critiche, altrimenti solo se WP_DEBUG è abilitato
        $is_critical = self::is_critical_operation($message);
        
        if (!$is_critical && (!defined('WP_DEBUG') || !WP_DEBUG)) {
            return;
        }
        
        // Log su file WordPress standard
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_message = '[Naval EGT Dropbox] ' . $message;
            if ($data !== null) {
                $log_message .= ' | Data: ' . wp_json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
            }
            error_log($log_message);
        }
        
        // Salva anche in opzione per la visualizzazione web
        $logs = get_option('naval_egt_dropbox_debug_logs', array());
        
        if (!is_array($logs)) {
            $logs = array();
        }
        
        $log_entry = array(
            'id' => uniqid('log_', true),
            'timestamp' => current_time('mysql'),
            'datetime_utc' => gmdate('Y-m-d H:i:s'),
            'message' => sanitize_text_field($message),
            'data' => $data,
            'level' => self::detect_log_level($message),
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'time_float' => microtime(true),
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_user_ip(),
            'request_uri' => sanitize_text_field($_SERVER['REQUEST_URI'] ?? ''),
            'user_agent' => sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)),
            'session_id' => self::get_session_id(),
            'is_ajax' => wp_doing_ajax(),
            'is_admin' => is_admin(),
            'is_critical' => $is_critical,
            'backtrace' => $is_critical ? wp_debug_backtrace_summary() : null
        );
        
        $logs[] = $log_entry;
        
        // Mantieni solo gli ultimi 150 log per evitare che l'opzione diventi troppo grande
        if (count($logs) > 150) {
            $logs = array_slice($logs, -150);
        }
        
        update_option('naval_egt_dropbox_debug_logs', $logs);
        
        // Reset cache
        self::$logs_cache = null;
        
        // Salva log critici in file separato
        if ($is_critical) {
            self::save_critical_log($log_entry);
        }
        
        // Trigger hook per altre estensioni
        do_action('naval_egt_debug_log', $log_entry);
    }
    
    /**
     * Ottieni tutti i log salvati con cache
     */
    public static function get_logs($limit = 50, $level_filter = null) {
        // Usa cache se disponibile
        if (self::$logs_cache !== null && $limit === 50 && $level_filter === null) {
            return self::$logs_cache;
        }
        
        $logs_option = get_option('naval_egt_dropbox_debug_logs', array());
        
        if (!is_array($logs_option)) {
            return array();
        }
        
        // Ordina per timestamp (più recenti per primi)
        usort($logs_option, function($a, $b) {
            $time_a = strtotime($a['timestamp'] ?? '1970-01-01');
            $time_b = strtotime($b['timestamp'] ?? '1970-01-01');
            return $time_b - $time_a;
        });
        
        // Filtra per livello se specificato
        if ($level_filter !== null) {
            $logs_option = array_filter($logs_option, function($log) use ($level_filter) {
                return ($log['level'] ?? 'info') === $level_filter;
            });
        }
        
        // Limita il numero di risultati
        $result = array_slice($logs_option, 0, $limit);
        
        // Cache solo per query standard
        if ($limit === 50 && $level_filter === null) {
            self::$logs_cache = $result;
        }
        
        return $result;
    }
    
    /**
     * Pulisci tutti i log
     */
    public static function clear_logs() {
        update_option('naval_egt_dropbox_debug_logs', array());
        
        // Reset cache
        self::$logs_cache = null;
        
        // Log dell'operazione di pulizia
        self::debug_log('Debug logs cleared by admin', array(
            'action' => 'clear_logs',
            'timestamp' => current_time('mysql'),
            'user' => wp_get_current_user()->user_login ?? 'unknown'
        ));
        
        return true;
    }
    
    /**
     * Ottieni statistiche dei log
     */
    public static function get_log_stats() {
        $logs = self::get_logs(1000); // Analizza fino a 1000 log
        
        $stats = array(
            'total_logs' => count($logs),
            'last_log_time' => null,
            'log_types' => array(),
            'log_levels' => array(),
            'memory_usage_avg' => 0,
            'memory_usage_peak' => 0,
            'errors_count' => 0,
            'success_count' => 0,
            'critical_count' => 0,
            'ajax_requests' => 0,
            'admin_requests' => 0,
            'unique_users' => array(),
            'timespan_hours' => 0
        );
        
        if (!empty($logs)) {
            $stats['last_log_time'] = $logs[0]['timestamp'] ?? null;
            
            // Calcola timespan
            $first_log_time = end($logs)['timestamp'] ?? null;
            if ($stats['last_log_time'] && $first_log_time) {
                $stats['timespan_hours'] = round((strtotime($stats['last_log_time']) - strtotime($first_log_time)) / 3600, 2);
            }
            
            $total_memory = 0;
            $peak_memory = 0;
            
            foreach ($logs as $log) {
                // Conta i tipi di log basandosi sulla prima parola del messaggio
                $first_word = strtok($log['message'] ?? '', ' ');
                if ($first_word) {
                    $stats['log_types'][$first_word] = ($stats['log_types'][$first_word] ?? 0) + 1;
                }
                
                // Conta i livelli
                $level = $log['level'] ?? 'info';
                $stats['log_levels'][$level] = ($stats['log_levels'][$level] ?? 0) + 1;
                
                // Calcola statistiche memoria
                $memory = intval($log['memory_usage'] ?? 0);
                $total_memory += $memory;
                $peak_memory = max($peak_memory, $memory);
                
                // Conta errori e successi
                $message = strtolower($log['message'] ?? '');
                if ($log['level'] === 'error' || strpos($message, 'error') !== false || strpos($message, 'fail') !== false) {
                    $stats['errors_count']++;
                } elseif (strpos($message, 'success') !== false || strpos($message, 'complete') !== false) {
                    $stats['success_count']++;
                }
                
                // Conta critici
                if ($log['is_critical'] ?? false) {
                    $stats['critical_count']++;
                }
                
                // Conta AJAX e admin
                if ($log['is_ajax'] ?? false) {
                    $stats['ajax_requests']++;
                }
                if ($log['is_admin'] ?? false) {
                    $stats['admin_requests']++;
                }
                
                // Raccogli utenti unici
                $user_id = $log['user_id'] ?? 0;
                if ($user_id > 0) {
                    $stats['unique_users'][$user_id] = true;
                }
            }
            
            $stats['memory_usage_avg'] = $total_memory / count($logs);
            $stats['memory_usage_peak'] = $peak_memory;
            $stats['unique_users_count'] = count($stats['unique_users']);
            unset($stats['unique_users']); // Rimuovi l'array, tieni solo il conteggio
        }
        
        return $stats;
    }
    
    /**
     * Esporta log per debug
     */
    public static function export_logs() {
        $logs = self::get_logs(1000);
        $stats = self::get_log_stats();
        
        $export_data = array(
            'exported_at' => current_time('mysql'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'plugin_version' => '1.0', // Aggiorna con la versione corretta
            'total_logs' => count($logs),
            'logs' => $logs,
            'statistics' => $stats,
            'system_info' => array(
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
                'wp_debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
                'wp_debug_display' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : false,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'server_os' => PHP_OS,
                'curl_version' => function_exists('curl_version') ? curl_version()['version'] : 'Not available',
                'openssl_version' => OPENSSL_VERSION_TEXT ?? 'Not available'
            ),
            'wordpress_info' => array(
                'multisite' => is_multisite(),
                'site_url' => get_site_url(),
                'home_url' => get_home_url(),
                'admin_email' => get_option('admin_email'),
                'timezone' => get_option('timezone_string') ?: 'UTC',
                'date_format' => get_option('date_format'),
                'time_format' => get_option('time_format')
            )
        );
        
        return $export_data;
    }
    
    /**
     * Log errore con stack trace
     */
    public static function log_error($message, $exception = null, $context = array()) {
        $error_data = array(
            'error_message' => $message,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'backtrace' => wp_debug_backtrace_summary()
        );
        
        if ($exception && $exception instanceof Exception) {
            $error_data['exception'] = array(
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            );
        }
        
        self::debug_log('ERROR: ' . $message, $error_data);
        
        // Log anche su file di errore di WordPress
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[Naval EGT ERROR] ' . $message . ' | Context: ' . wp_json_encode($context));
        }
    }
    
    /**
     * Log operazione Dropbox con timing
     */
    public static function log_dropbox_operation($operation, $start_time, $result, $context = array()) {
        $duration = microtime(true) - $start_time;
        
        $log_data = array(
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $result['success'] ?? false,
            'result' => $result,
            'context' => $context,
            'memory_before' => $context['memory_before'] ?? null,
            'memory_after' => memory_get_usage(),
            'peak_memory' => memory_get_peak_usage()
        );
        
        $status = $result['success'] ? 'SUCCESS' : 'FAILED';
        $message = "Dropbox {$operation} {$status} in {$log_data['duration_ms']}ms";
        
        self::debug_log($message, $log_data);
    }
    
    /**
     * Log performance metrics
     */
    public static function log_performance($operation, $metrics = array()) {
        $performance_data = array_merge(array(
            'operation' => $operation,
            'timestamp' => current_time('mysql'),
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'memory_limit' => ini_get('memory_limit'),
            'time_limit' => ini_get('max_execution_time'),
            'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null
        ), $metrics);
        
        self::debug_log("PERFORMANCE: {$operation}", $performance_data);
    }
    
    /**
     * Determina se un'operazione è critica
     */
    private static function is_critical_operation($message) {
        $critical_keywords = array(
            'error',
            'fail',
            'critical',
            'fatal',
            'exception',
            'crash',
            'corrupt',
            'invalid token',
            'connection failed',
            'timeout'
        );
        
        $message_lower = strtolower($message);
        
        foreach ($critical_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Rileva livello di log dal messaggio
     */
    private static function detect_log_level($message) {
        $message = strtolower($message);
        
        if (strpos($message, 'error') !== false || strpos($message, 'fail') !== false || strpos($message, 'critical') !== false) {
            return 'error';
        } elseif (strpos($message, 'warning') !== false || strpos($message, 'warn') !== false) {
            return 'warning';
        } elseif (strpos($message, 'success') !== false || strpos($message, 'complete') !== false || strpos($message, 'ok') !== false) {
            return 'success';
        } elseif (strpos($message, 'debug') !== false || strpos($message, 'test') !== false) {
            return 'debug';
        } else {
            return 'info';
        }
    }
    
    /**
     * Ottieni IP dell'utente
     */
    private static function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                        'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Ottieni ID sessione
     */
    private static function get_session_id() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return session_id();
        }
        
        // Usa cookie WordPress come fallback
        $cookies = array('wordpress_logged_in_', 'wordpress_sec_', 'wp-settings-');
        foreach ($cookies as $cookie_prefix) {
            foreach ($_COOKIE as $name => $value) {
                if (strpos($name, $cookie_prefix) === 0) {
                    return substr(md5($value), 0, 8);
                }
            }
        }
        
        return substr(md5($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 8);
    }
    
    /**
     * Salva log critico in file separato
     */
    private static function save_critical_log($log_entry) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/naval-egt-critical.log';
        
        $log_line = sprintf(
            "[%s] CRITICAL: %s | Data: %s | User: %d | IP: %s\n",
            $log_entry['timestamp'],
            $log_entry['message'],
            wp_json_encode($log_entry['data'] ?? ''),
            $log_entry['user_id'] ?? 0,
            $log_entry['ip_address'] ?? 'unknown'
        );
        
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Mantieni solo le ultime 100 righe
        $lines = file($log_file);
        if (count($lines) > 100) {
            file_put_contents($log_file, implode('', array_slice($lines, -100)));
        }
    }
    
    /**
     * Pulizia automatica log vecchi
     */
    public static function cleanup_old_logs() {
        $logs = get_option('naval_egt_dropbox_debug_logs', array());
        
        if (!is_array($logs) || count($logs) <= 50) {
            return; // Non c'è bisogno di pulizia
        }
        
        // Rimuovi log più vecchi di 7 giorni
        $cutoff_time = strtotime('-7 days');
        
        $cleaned_logs = array_filter($logs, function($log) use ($cutoff_time) {
            $log_time = strtotime($log['timestamp'] ?? '1970-01-01');
            return $log_time > $cutoff_time;
        });
        
        // Se abbiamo rimosso qualcosa, salva
        if (count($cleaned_logs) < count($logs)) {
            update_option('naval_egt_dropbox_debug_logs', array_values($cleaned_logs));
            self::$logs_cache = null; // Reset cache
            
            $removed_count = count($logs) - count($cleaned_logs);
            self::debug_log("Cleaned up {$removed_count} old debug logs", array(
                'removed_count' => $removed_count,
                'remaining_count' => count($cleaned_logs)
            ));
        }
    }
    
    /**
     * Salva log critici allo shutdown
     */
    public static function save_critical_logs() {
        // Questo metodo viene chiamato allo shutdown per salvare eventuali log critici rimasti
        // Al momento non fa nulla, ma può essere esteso in futuro
    }
    
    /**
     * Verifica salute del sistema di logging
     */
    public static function health_check() {
        $health = array(
            'logs_writable' => true,
            'memory_ok' => true,
            'debug_enabled' => defined('WP_DEBUG') && WP_DEBUG,
            'log_file_writable' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'recent_logs_count' => 0,
            'errors_in_last_hour' => 0,
            'warnings' => array(),
            'recommendations' => array()
        );
        
        try {
            // Test scrittura log
            self::debug_log('Health check test log', array('test' => true));
            
            // Conta log recenti
            $logs = self::get_logs();
            $health['recent_logs_count'] = count($logs);
            
            // Conta errori nell'ultima ora
            $one_hour_ago = strtotime('-1 hour');
            foreach ($logs as $log) {
                $log_time = strtotime($log['timestamp'] ?? '1970-01-01');
                if ($log_time > $one_hour_ago) {
                    $level = $log['level'] ?? 'info';
                    if ($level === 'error') {
                        $health['errors_in_last_hour']++;
                    }
                }
            }
            
            // Verifica memoria
            $memory_usage = memory_get_usage();
            $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
            $memory_percent = ($memory_usage / $memory_limit) * 100;
            
            if ($memory_percent > 80) {
                $health['memory_ok'] = false;
                $health['warnings'][] = "Utilizzo memoria alto: {$memory_percent}%";
            }
            
            // Raccomandazioni
            if (!$health['debug_enabled']) {
                $health['recommendations'][] = 'Abilitare WP_DEBUG per logging completo';
            }
            
            if ($health['errors_in_last_hour'] > 10) {
                $health['warnings'][] = "Molti errori nell'ultima ora: {$health['errors_in_last_hour']}";
            }
            
            if (count($logs) > 100) {
                $health['recommendations'][] = 'Considerare la pulizia dei log per prestazioni migliori';
            }
            
        } catch (Exception $e) {
            $health['logs_writable'] = false;
            $health['warnings'][] = 'Errore nella scrittura dei log: ' . $e->getMessage();
        }
        
        $health['overall_status'] = empty($health['warnings']) ? 'good' : 'warning';
        
        return $health;
    }
    
    /**
     * Formatta log per visualizzazione
     */
    public static function format_log_for_display($log) {
        if (!is_array($log)) {
            return array();
        }
        
        $formatted = array(
            'timestamp' => $log['timestamp'] ?? 'N/A',
            'message' => $log['message'] ?? 'No message',
            'level' => $log['level'] ?? 'info',
            'duration' => null,
            'memory' => isset($log['memory_usage']) ? size_format($log['memory_usage']) : 'N/A',
            'user_info' => '',
            'data_summary' => '',
            'is_critical' => $log['is_critical'] ?? false,
            'is_ajax' => $log['is_ajax'] ?? false,
            'ip_address' => $log['ip_address'] ?? 'unknown'
        );
        
        // Estrai durata se presente
        if (isset($log['data']['duration_ms'])) {
            $formatted['duration'] = $log['data']['duration_ms'] . 'ms';
        }
        
        // Info utente
        if (isset($log['user_id']) && $log['user_id'] > 0) {
            $user = get_user_by('id', $log['user_id']);
            $formatted['user_info'] = $user ? $user->user_login : "User #{$log['user_id']}";
        }
        
        // Riassunto dati
        if (isset($log['data']) && is_array($log['data'])) {
            $data_keys = array_keys($log['data']);
            $formatted['data_summary'] = implode(', ', array_slice($data_keys, 0, 3));
            if (count($data_keys) > 3) {
                $formatted['data_summary'] .= '...';
            }
        }
        
        return $formatted;
    }
    
    /**
     * Ricerca nei log
     */
    public static function search_logs($query, $filters = array()) {
        $logs = self::get_logs(1000);
        $results = array();
        
        $query_lower = strtolower($query);
        
        foreach ($logs as $log) {
            $match = false;
            
            // Cerca nel messaggio
            if (strpos(strtolower($log['message'] ?? ''), $query_lower) !== false) {
                $match = true;
            }
            
            // Cerca nei dati (se sono array)
            if (!$match && isset($log['data']) && is_array($log['data'])) {
                $data_string = strtolower(wp_json_encode($log['data']));
                if (strpos($data_string, $query_lower) !== false) {
                    $match = true;
                }
            }
            
            // Applica filtri aggiuntivi
            if ($match && !empty($filters)) {
                // Filtro per livello
                if (isset($filters['level']) && $log['level'] !== $filters['level']) {
                    $match = false;
                }
                
                // Filtro per data
                if (isset($filters['date_from']) && strtotime($log['timestamp']) < strtotime($filters['date_from'])) {
                    $match = false;
                }
                
                if (isset($filters['date_to']) && strtotime($log['timestamp']) > strtotime($filters['date_to'])) {
                    $match = false;
                }
                
                // Filtro per utente
                if (isset($filters['user_id']) && $log['user_id'] !== $filters['user_id']) {
                    $match = false;
                }
            }
            
            if ($match) {
                $results[] = $log;
            }
        }
        
        return $results;
    }
}

?>