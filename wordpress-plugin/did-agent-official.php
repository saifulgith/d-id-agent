<?php
/**
 * Plugin Name: D-ID Agent Official
 * Description: D-ID Agent integration using official SDK with auto-loading UI and greeting
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DIDAgentOfficial {
    private $backend_url;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('did_agent_official', array($this, 'render_agent_shortcode'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    public function init() {
        $this->backend_url = get_option('did_agent_backend_url', '');
    }
    
    public function enqueue_scripts() {
        // Load D-ID SDK from official CDN
        wp_enqueue_script(
            'd-id-sdk',
            'https://cdn.jsdelivr.net/npm/@d-id/client-sdk@latest/dist/index.js',
            array(),
            '2.0.0',
            true
        );
        
        // Configure D-ID SDK
        wp_add_inline_script('d-id-sdk', '
            window.didAgentConfig = {
                backendUrl: "' . esc_js($this->backend_url) . '",
                ajaxUrl: "' . admin_url('admin-ajax.php') . '",
                nonce: "' . wp_create_nonce('did_agent_nonce') . '"
            };
            
            console.log("🎯 D-ID Agent Official - Using Official SDK");
        ');
    }
    
    public function render_agent_shortcode($atts) {
        $atts = shortcode_atts(array(
            'agent_id' => '',
            'width' => '100%',
            'height' => '600px'
        ), $atts);
        
        if (empty($atts['agent_id'])) {
            return '<div style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; color: #dc3545;">Error: Agent ID is required. Use [did_agent_official agent_id="your_agent_id"]</div>';
        }
        
        if (empty($this->backend_url)) {
            return '<div style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; color: #dc3545;">Error: Backend URL not configured. Please set it in the admin settings.</div>';
        }
        
        $agent_id = esc_attr($atts['agent_id']);
        $width = esc_attr($atts['width']);
        $height = esc_attr($atts['height']);
        
        ob_start();
        ?>
        <div id="did-agent-official-<?php echo $agent_id; ?>" 
             style="width: <?php echo $width; ?>; height: <?php echo $height; ?>; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12); position: relative; background: #000;">
            
            <!-- Loading State -->
            <div id="loading-<?php echo $agent_id; ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; position: absolute; inset: 0px; z-index: 10;">
                <div style="width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.3); border-top: 4px solid white; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 16px;"></div>
                <p style="margin: 0; font-size: 16px; font-weight: 500;">Connecting to AI Agent...</p>
            </div>
            
            <!-- Error State -->
            <div id="error-<?php echo $agent_id; ?>" style="display: none; padding: 20px; background: #f8d7da; color: #721c24; text-align: center; border-radius: 8px; margin: 20px; position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 10;">
                <p style="margin: 0;">Failed to connect to AI Agent. Please try again later.</p>
            </div>
            
            <!-- SDK container (video + UI will be injected here) -->
            <div id="sdk-container-<?php echo $agent_id; ?>" style="width: 100%; height: 100%; position: relative;"></div>
        </div>
        
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        
        <script>
        document.addEventListener("DOMContentLoaded", async () => {
            const agentId = '<?php echo $agent_id; ?>';
            const container = document.getElementById('did-agent-official-' + agentId);
            const loadingDiv = document.getElementById('loading-' + agentId);
            const errorDiv = document.getElementById('error-' + agentId);
            const sdkContainer = document.getElementById('sdk-container-' + agentId);
            
            try {
                console.log('🚀 Initializing D-ID Agent with Official SDK:', agentId);
                
                // Wait for D-ID SDK to load
                let retries = 0;
                while (typeof window.createAgentManager === 'undefined' && retries < 30) {
                    await new Promise(resolve => setTimeout(resolve, 200));
                    retries++;
                }
                
                if (typeof window.createAgentManager === 'undefined') {
                    throw new Error('D-ID SDK not loaded after 30 retries');
                }
                
                console.log('✅ D-ID SDK loaded successfully');
                
                // Get client key from backend
                console.log('🔑 Getting client key from backend...');
                const response = await fetch(window.didAgentConfig.backendUrl + '/api/client-key', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        agentId: agentId,
                        allowed_domains: [window.location.origin]
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Failed to get client key: ' + response.status);
                }
                
                const data = await response.json();
                const clientKey = data.client_key || data.clientKey || data.key || data.token;
                
                if (!clientKey) {
                    throw new Error('No client key received from backend');
                }
                
                console.log('✅ Client key received successfully');
                
                // Intercept fetch requests to redirect D-ID API calls to our backend
                const originalFetch = window.fetch;
                window.fetch = function(url, options) {
                    if (typeof url === 'string' && url.indexOf('api.d-id.com') !== -1) {
                        const newUrl = url.replace('https://api.d-id.com', window.didAgentConfig.backendUrl + '/api');
                        console.log('🔄 Redirecting API call:', url, '->', newUrl);
                        
                        options = options || {};
                        options.headers = options.headers || {};
                        options.headers['Authorization'] = 'Client-Key ' + clientKey;
                        
                        return originalFetch(newUrl, options);
                    }
                    return originalFetch(url, options);
                };
                
                // Intercept WebSocket connections
                const originalWebSocket = window.WebSocket;
                window.WebSocket = function(url, protocols) {
                    if (typeof url === 'string' && url.indexOf('notifications.d-id.com') !== -1) {
                        const newUrl = url.replace('wss://notifications.d-id.com', window.didAgentConfig.backendUrl.replace('https://', 'wss://') + '/api/notifications');
                        console.log('🔄 Redirecting WebSocket:', url, '->', newUrl);
                        return new originalWebSocket(newUrl, protocols);
                    }
                    return new originalWebSocket(url, protocols);
                };
                
                // Create agent manager with official SDK
                console.log('🎨 Creating D-ID Agent Manager with official SDK...');
                
                const auth = { type: 'key', clientKey: clientKey };
                
                const callbacks = {
                    onConnectionStateChange: (state) => {
                        console.log('🔗 Connection state:', state);
                        if (state === 'connected') {
                            console.log('✅ Agent connected successfully!');
                            loadingDiv.style.display = 'none';
                        }
                    },
                    onError: (error, errorData) => {
                        console.error('❌ Agent error:', error, errorData);
                        loadingDiv.style.display = 'none';
                        errorDiv.style.display = 'flex';
                        errorDiv.querySelector('p').textContent = error.message || 'Agent connection failed';
                    },
                    onNewMessage: (messages, type) => {
                        console.log('💬 New message:', messages, type);
                    }
                };
                
                const agentManager = await window.createAgentManager(agentId, {
                    auth,
                    callbacks,
                    container: sdkContainer
                });
                
                console.log('✅ Agent manager created');
                
                // Connect automatically
                await agentManager.connect();
                
                console.log('✅ Agent connected successfully!');
                
                // Send greeting message
                setTimeout(() => {
                    console.log('👋 Sending greeting message...');
                    agentManager.chat("Hello, how can I help you today?");
                }, 2000);
                
                console.log('🎉 D-ID Agent Official is ready!');
                
            } catch (error) {
                console.error('❌ Failed to initialize agent:', error);
                loadingDiv.style.display = 'none';
                errorDiv.style.display = 'flex';
                errorDiv.querySelector('p').textContent = error.message;
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function add_admin_menu() {
        add_options_page(
            'D-ID Agent Official Settings',
            'D-ID Agent Official',
            'manage_options',
            'did-agent-official',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            update_option('did_agent_backend_url', sanitize_url($_POST['backend_url']));
            $this->backend_url = get_option('did_agent_backend_url', '');
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>D-ID Agent Official Settings</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Backend URL</th>
                        <td>
                            <input type="url" name="backend_url" value="<?php echo esc_attr($this->backend_url); ?>" class="regular-text" placeholder="https://your-backend.onrender.com" required />
                            <p class="description">Enter your Render backend URL (e.g., https://d-id-agent-1sdx.onrender.com)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <h2>Usage</h2>
            <p>Use the shortcode <code>[did_agent_official agent_id="your_agent_id"]</code> to display the D-ID agent with official SDK.</p>
            
            <h3>Example</h3>
            <code>[did_agent_official agent_id="v2_agt_aKkqeO6X" width="800px" height="600px"]</code>
            
            <h2>Features</h2>
            <ul>
                <li>✅ Uses official D-ID SDK</li>
                <li>✅ Auto-loading UI with face + voice</li>
                <li>✅ Automatic greeting message</li>
                <li>✅ Built-in chat interface</li>
                <li>✅ Professional appearance</li>
            </ul>
        </div>
        <?php
    }
}

// Initialize the plugin
new DIDAgentOfficial();
?>
