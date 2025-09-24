<?php
/**
 * Classe per la gestione delle email - VERSIONE CORRETTA CON DEBUG E FIX COMPLETO
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Email {
    
    private static $instance = null;
    private static $debug_mode = true; // Attiva debug per troubleshooting
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Non modificare il content type globalmente, lo facciamo per ogni email
        add_action('phpmailer_init', array($this, 'configure_phpmailer'));
    }
    
    /**
     * Configura PHPMailer per migliorare la deliverability
     */
    public function configure_phpmailer($phpmailer) {
        $phpmailer->CharSet = 'UTF-8';
        $phpmailer->isHTML(true);
        
        if (self::$debug_mode) {
            $phpmailer->SMTPDebug = 1;
        }
    }
    
    /**
     * Invia email di benvenuto a nuovo utente - VERSIONE CORRETTA
     */
    public static function send_welcome_email($user_data) {
        if (self::$debug_mode) {
            error_log('Naval EGT Email: === INIZIO INVIO EMAIL BENVENUTO ===');
            error_log('Naval EGT Email: Destinatario: ' . $user_data['email']);
        }
        
        $email_enabled = Naval_EGT_Database::get_setting('email_notifications', '1');
        if ($email_enabled !== '1') {
            if (self::$debug_mode) {
                error_log('Naval EGT Email: Email notifications DISABILITATE');
            }
            return false;
        }
        
        $template = Naval_EGT_Database::get_setting('welcome_email_template', self::get_default_welcome_template());
        
        // Sostituzioni placeholder
        $placeholders = array(
            '{nome}' => $user_data['nome'],
            '{cognome}' => $user_data['cognome'],
            '{user_code}' => $user_data['user_code'],
            '{username}' => $user_data['username'],
            '{email}' => $user_data['email'],
            '{ragione_sociale}' => isset($user_data['ragione_sociale']) ? $user_data['ragione_sociale'] : '',
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => home_url(),
            '{login_url}' => self::get_login_url()
        );
        
        $subject = 'Richiesta registrazione ricevuta - Naval EGT';
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
        
        // Configura headers per HTML
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        $headers = array(
            'From: Naval EGT <noreply@' . self::get_domain() . '>',
            'Reply-To: Supporto Naval EGT <tecnica@naval.it>'
        );
        
        if (self::$debug_mode) {
            error_log('Naval EGT Email: Subject: ' . $subject);
            error_log('Naval EGT Email: Headers: ' . print_r($headers, true));
        }
        
        $sent = wp_mail($user_data['email'], $subject, $message, $headers);
        
        // Rimuovi il filtro content type
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        if (self::$debug_mode) {
            error_log('Naval EGT Email: Welcome email sent: ' . ($sent ? 'YES' : 'NO'));
            if (!$sent) {
                global $phpmailer;
                if (isset($phpmailer->ErrorInfo)) {
                    error_log('Naval EGT Email: PHPMailer Error: ' . $phpmailer->ErrorInfo);
                }
            }
        }
        
        return $sent;
    }
    
    /**
     * Invia notifica admin per nuova registrazione - VERSIONE COMPLETAMENTE CORRETTA
     */
    public static function send_admin_notification($user_data) {
        if (self::$debug_mode) {
            error_log('Naval EGT Email: === INIZIO INVIO NOTIFICA ADMIN ===');
        }
        
        // Email amministratore - FISSO
        $admin_email = 'navalegtsito@gmail.com';
        
        if (self::$debug_mode) {
            error_log('Naval EGT Email: Admin email destinatario: ' . $admin_email);
        }
        
        $subject = 'NUOVA REGISTRAZIONE - Naval EGT - ' . $user_data['nome'] . ' ' . $user_data['cognome'];
        
        // Messaggio HTML ottimizzato
        $message = self::build_admin_notification_html($user_data);
        
        // Configura headers per HTML
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        $headers = array(
            'From: Sistema Naval EGT <noreply@' . self::get_domain() . '>',
            'Reply-To: Sistema Naval EGT <navalegtsito@gmail.com>',
            'X-Priority: 1',
            'Importance: High'
        );
        
        if (self::$debug_mode) {
            error_log('Naval EGT Email: Admin notification - Subject: ' . $subject);
            error_log('Naval EGT Email: Admin notification - Headers: ' . print_r($headers, true));
        }
        
        // Invia email
        $sent = wp_mail($admin_email, $subject, $message, $headers);
        
        // Rimuovi il filtro content type
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        if (self::$debug_mode) {
            error_log('Naval EGT Email: Admin notification sent: ' . ($sent ? 'SUCCESS' : 'FAILED'));
            if (!$sent) {
                global $phpmailer;
                if (isset($phpmailer->ErrorInfo)) {
                    error_log('Naval EGT Email: PHPMailer Error: ' . $phpmailer->ErrorInfo);
                }
                // Test alternativo con email semplice
                self::send_admin_notification_fallback($user_data, $admin_email);
            }
        }
        
        return $sent;
    }
    
    /**
     * Fallback per notifica admin con email semplice
     */
    private static function send_admin_notification_fallback($user_data, $admin_email) {
        if (self::$debug_mode) {
            error_log('Naval EGT Email: Tentativo fallback con email di testo semplice');
        }
        
        $subject = 'NUOVA REGISTRAZIONE Naval EGT';
        $message = "Nuova registrazione ricevuta:\n\n";
        $message .= "Nome: " . $user_data['nome'] . " " . $user_data['cognome'] . "\n";
        $message .= "Email: " . $user_data['email'] . "\n";
        $message .= "Username: " . $user_data['username'] . "\n";
        $message .= "Codice: " . $user_data['user_code'] . "\n";
        $message .= "Data: " . date('d/m/Y H:i:s') . "\n\n";
        $message .= "L'utente e' in stato SOSPESO e richiede attivazione manuale.";
        
        $headers = array('From: Naval EGT <noreply@' . self::get_domain() . '>');
        
        $sent = wp_mail($admin_email, $subject, $message, $headers);
        
        if (self::$debug_mode) {
            error_log('Naval EGT Email: Fallback notification sent: ' . ($sent ? 'SUCCESS' : 'FAILED'));
        }
        
        return $sent;
    }
    
    /**
     * Costruisce HTML per notifica admin
     */
    private static function build_admin_notification_html($user_data) {
        $dashboard_url = admin_url('admin.php?page=naval-egt&tab=users');
        
        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Nuova Registrazione Naval EGT</title>
            </head>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;">
                <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    
                    <div style="background: linear-gradient(135deg, #1e40af 0%%, #3b82f6 100%%); color: white; padding: 30px; text-align: center;">
                        <h1 style="margin: 0; font-size: 24px;">🚢 Naval EGT</h1>
                        <h2 style="margin: 10px 0 0 0; font-size: 18px; font-weight: normal;">Nuova Registrazione Utente</h2>
                    </div>
                    
                    <div style="padding: 30px;">
                        <div style="background: #eff6ff; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #3b82f6;">
                            <h3 style="margin: 0 0 15px 0; color: #1e40af; font-size: 18px;">📋 Dettagli Registrazione</h3>
                            
                            <table style="width: 100%%; border-collapse: collapse; font-size: 14px;">
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Nome:</td>
                                    <td style="padding: 8px 0; color: #1f2937;">%s %s</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Email:</td>
                                    <td style="padding: 8px 0;"><a href="mailto:%s" style="color: #3b82f6; text-decoration: none;">%s</a></td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Username:</td>
                                    <td style="padding: 8px 0; color: #1f2937;">%s</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Codice Utente:</td>
                                    <td style="padding: 8px 0; color: #3b82f6; font-weight: bold;">%s</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Telefono:</td>
                                    <td style="padding: 8px 0; color: #1f2937;">%s</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Azienda:</td>
                                    <td style="padding: 8px 0; color: #1f2937;">%s</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">P.IVA:</td>
                                    <td style="padding: 8px 0; color: #1f2937;">%s</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div style="background: #fef3c7; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 25px;">
                            <h4 style="margin: 0 0 10px 0; color: #92400e; font-size: 16px;">⚠️ Azione Richiesta</h4>
                            <p style="margin: 0; color: #92400e; font-size: 14px;"><strong>L\'utente è stato registrato in stato SOSPESO e richiede attivazione manuale.</strong></p>
                        </div>
                        
                        <div style="text-align: center; margin-bottom: 20px;">
                            <a href="%s" style="background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 14px;">
                                👤 Vai alla Dashboard Utenti
                            </a>
                        </div>
                        
                        <div style="background: #f9fafb; padding: 15px; border-radius: 6px; text-align: center; font-size: 12px; color: #6b7280;">
                            <p style="margin: 0;"><strong>Data registrazione:</strong> %s</p>
                            <p style="margin: 5px 0 0 0;"><strong>IP:</strong> %s</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>',
            esc_html($user_data['nome']),
            esc_html($user_data['cognome']),
            esc_html($user_data['email']),
            esc_html($user_data['email']),
            esc_html($user_data['username']),
            esc_html($user_data['user_code']),
            !empty($user_data['telefono']) ? esc_html($user_data['telefono']) : 'Non specificato',
            !empty($user_data['ragione_sociale']) ? esc_html($user_data['ragione_sociale']) : 'Non specificata',
            !empty($user_data['partita_iva']) ? esc_html($user_data['partita_iva']) : 'Non specificata',
            esc_url($dashboard_url),
            date('d/m/Y H:i:s'),
            $_SERVER['REMOTE_ADDR'] ?? 'Non disponibile'
        );
    }
    
    /**
     * Invia email di attivazione account
     */
    public static function send_activation_email($user_data) {
        if (self::$debug_mode) {
            error_log('Naval EGT Email: === INIZIO INVIO EMAIL ATTIVAZIONE ===');
            error_log('Naval EGT Email: Destinatario: ' . $user_data['email']);
        }
        
        $email_enabled = Naval_EGT_Database::get_setting('email_notifications', '1');
        if ($email_enabled !== '1') {
            if (self::$debug_mode) {
                error_log('Naval EGT Email: Email notifications DISABILITATE per attivazione');
            }
            return false;
        }
        
        $subject = 'Account attivato - Naval EGT';
        $message = self::build_activation_email_html($user_data);
        
        // Configura headers per HTML
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        $headers = array(
            'From: Naval EGT <noreply@' . self::get_domain() . '>',
            'Reply-To: Supporto Naval EGT <tecnica@naval.it>'
        );
        
        $sent = wp_mail($user_data['email'], $subject, $message, $headers);
        
        // Rimuovi il filtro content type
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        if (self::$debug_mode) {
            error_log('Naval EGT Email: Activation email sent: ' . ($sent ? 'SUCCESS' : 'FAILED'));
        }
        
        return $sent;
    }
    
    /**
     * Costruisce HTML per email di attivazione
     */
    private static function build_activation_email_html($user_data) {
        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Account Attivato</title>
            </head>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;">
                <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    
                    <div style="background: linear-gradient(135deg, #10b981 0%%, #059669 100%%); color: white; padding: 30px; text-align: center;">
                        <h1 style="margin: 0; font-size: 24px;">🚢 Naval EGT</h1>
                        <h2 style="margin: 10px 0 0 0; font-size: 18px; font-weight: normal;">Account Attivato!</h2>
                    </div>
                    
                    <div style="padding: 30px;">
                        <div style="background: #d1fae5; padding: 20px; border-radius: 8px; margin-bottom: 25px; text-align: center; border-left: 4px solid #10b981;">
                            <h3 style="margin: 0 0 15px 0; color: #065f46; font-size: 18px;">✅ Il tuo account è attivo!</h3>
                            <p style="margin: 0; color: #065f46; font-size: 16px;">
                                Ciao <strong>%s %s</strong>,<br><br>
                                Il tuo account è stato attivato con successo dal nostro team.<br>
                                Ora puoi accedere alla tua area riservata.
                            </p>
                        </div>
                        
                        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                            <h4 style="margin: 0 0 15px 0; color: #374151; font-size: 16px;">📋 I tuoi dati di accesso:</h4>
                            <table style="width: 100%%; font-size: 14px;">
                                <tr>
                                    <td style="padding: 5px 0; font-weight: bold; color: #6b7280;">Username:</td>
                                    <td style="padding: 5px 0; color: #1f2937;">%s</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; font-weight: bold; color: #6b7280;">Email:</td>
                                    <td style="padding: 5px 0; color: #1f2937;">%s</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; font-weight: bold; color: #6b7280;">Codice Cliente:</td>
                                    <td style="padding: 5px 0; color: #3b82f6; font-weight: bold;">%s</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div style="text-align: center; margin-bottom: 25px;">
                            <a href="%s" style="background: #10b981; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 16px;">
                                🔐 Accedi alla tua Area Riservata
                            </a>
                        </div>
                        
                        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; color: #6b7280; font-size: 14px;">
                            <h4 style="color: #374151; font-size: 16px; margin: 0 0 10px 0;">Cosa puoi fare nella tua area:</h4>
                            <ul style="line-height: 1.8; margin: 0; padding-left: 20px;">
                                <li>Accedere alla tua cartella Dropbox dedicata</li>
                                <li>Scaricare documenti e file tecnici</li>
                                <li>Visualizzare le tue informazioni profilo</li>
                                <li>Contattare il supporto tecnico</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 20px; text-align: center; color: #6b7280; font-size: 12px; border-top: 1px solid #e5e7eb;">
                        <p style="margin: 0;">Naval Engineering & Green Technologies</p>
                        <p style="margin: 5px 0 0 0;">Per assistenza: <a href="mailto:tecnica@naval.it" style="color: #3b82f6;">tecnica@naval.it</a></p>
                    </div>
                </div>
            </body>
            </html>',
            esc_html($user_data['nome']),
            esc_html($user_data['cognome']),
            esc_html($user_data['username']),
            esc_html($user_data['email']),
            esc_html($user_data['user_code']),
            esc_url(self::get_login_url())
        );
    }
    
    /**
     * Template email di benvenuto predefinito
     */
    private static function get_default_welcome_template() {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;">
            <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                <div style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; padding: 30px; text-align: center;">
                    <h1 style="margin: 0; font-size: 24px;">🚢 Naval EGT</h1>
                    <h2 style="margin: 10px 0 0 0; font-size: 18px;">Richiesta ricevuta</h2>
                </div>
                
                <div style="padding: 30px;">
                    <div style="background: #eff6ff; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                        <h3 style="margin: 0 0 15px 0; color: #1e40af;">👋 Ciao {nome} {cognome}!</h3>
                        <p style="margin: 0; color: #1e40af;">
                            La tua richiesta di registrazione è stata ricevuta con successo.<br>
                            Il tuo account è in attesa di attivazione da parte del nostro team.
                        </p>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                        <h4 style="margin: 0 0 15px 0; color: #374151;">📋 I tuoi dati:</h4>
                        <p style="margin: 0; color: #6b7280;">
                            <strong>Username:</strong> {username}<br>
                            <strong>Email:</strong> {email}<br>
                            <strong>Codice Cliente:</strong> {user_code}
                        </p>
                    </div>
                    
                    <div style="background: #fef3c7; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                        <h4 style="margin: 0 0 10px 0; color: #92400e;">⏳ Prossimi passi</h4>
                        <p style="margin: 0; color: #92400e;">
                            Il nostro team verificherà la tua richiesta e attiverà il tuo account entro 24-48 ore.<br>
                            Riceverai un\'email di conferma quando l\'account sarà attivo.
                        </p>
                    </div>
                </div>
                
                <div style="background: #f8fafc; padding: 20px; text-align: center; color: #6b7280; font-size: 12px;">
                    <p style="margin: 0;">Naval Engineering & Green Technologies</p>
                    <p style="margin: 5px 0 0 0;">Per assistenza: tecnica@naval.it</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Ottiene dominio corrente per email
     */
    private static function get_domain() {
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return str_replace('www.', '', $domain);
    }
    
    /**
     * Ottiene URL di login
     */
    private static function get_login_url() {
        $login_page = Naval_EGT_Database::get_setting('login_page_url', '');
        if (empty($login_page)) {
            return home_url('/area-riservata/');
        }
        return $login_page;
    }
    
    /**
     * Test configurazione email con debug completo
     */
    public static function test_email_configuration($test_email) {
        if (self::$debug_mode) {
            error_log('Naval EGT Email: === TEST CONFIGURAZIONE EMAIL ===');
            error_log('Naval EGT Email: Test email destinatario: ' . $test_email);
        }
        
        $subject = 'Test Configurazione Email - Naval EGT';
        $message = '<h2>Test Email Naval EGT</h2>';
        $message .= '<p>Se ricevi questa email, la configurazione funziona correttamente.</p>';
        $message .= '<p><strong>Timestamp:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        $message .= '<p><strong>Server:</strong> ' . $_SERVER['HTTP_HOST'] . '</p>';
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        $headers = array(
            'From: Naval EGT Test <noreply@' . self::get_domain() . '>'
        );
        
        $sent = wp_mail($test_email, $subject, $message, $headers);
        
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        if (self::$debug_mode) {
            error_log('Naval EGT Email: Test email sent: ' . ($sent ? 'SUCCESS' : 'FAILED'));
            
            // Log configurazione WordPress mail
            error_log('Naval EGT Email: WordPress admin email: ' . get_option('admin_email'));
            error_log('Naval EGT Email: Blog name: ' . get_bloginfo('name'));
        }
        
        return $sent;
    }
    
    /**
     * Attiva/disattiva modalità debug
     */
    public static function set_debug_mode($enabled = true) {
        self::$debug_mode = $enabled;
    }
    
    /**
     * Invia email di reset password (placeholder per funzionalità futura)
     */
    public static function send_password_reset_email($user_data, $reset_token) {
        // Implementazione futura
        return true;
    }
}