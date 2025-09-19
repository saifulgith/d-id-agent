<?php
/**
 * Plugin Name: D-ID Agent Proper
 * Description: Proper D-ID Agent integration using SDK's built-in professional interface
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DIDAgentProper {
    private $backend_url;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('did_agent_proper', array($this, 'render_agent_shortcode'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    public function init() {
        $this->backend_url = get_option('did_agent_backend_url', '');
    }
    
    public function enqueue_scripts() {
        // Load D-ID SDK
        wp_enqueue_script(
            'did-sdk',
            'https://cdn.jsdelivr.net/npm/@d-id/client-sdk@latest/dist/index.js',
            array(),
            '1.0.0',
            true
        );
        
        // Add inline script for D-ID SDK configuration
        wp_add_inline_script('did-sdk', '
            // Configure D-ID SDK
            window.didAgentConfig = {
                backendUrl: "' . esc_js($this->backend_url) . '",
                ajaxUrl: "' . admin_url('admin-ajax.php') . '",
                nonce: "' . wp_create_nonce('did_agent_nonce') . '"
            };
            
            console.log("🎯 D-ID Agent Proper - Using Official SDK Interface");
        ');
    }
    
    public function render_agent_shortcode($atts) {
        $atts = shortcode_atts(array(
            'agent_id' => '',
            'width' => '100%',
            'height' => '600px'
        ), $atts);
        
        if (empty($atts['agent_id'])) {
            return '<div style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; color: #dc3545;">Error: Agent ID is required. Use [did_agent_proper agent_id="your_agent_id"]</div>';
        }
        
        if (empty($this->backend_url)) {
            return '<div style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; color: #dc3545;">Error: Backend URL not configured. Please set it in the admin settings.</div>';
        }
        
        $agent_id = esc_attr($atts['agent_id']);
        $width = esc_attr($atts['width']);
        $height = esc_attr($atts['height']);
        
        ob_start();
        ?>
        <div id="did-agent-proper-<?php echo $agent_id; ?>" 
             style="width: <?php echo $width; ?>; height: <?php echo $height; ?>; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);">
            
            <!-- Loading State -->
            <div id="loading-<?php echo $agent_id; ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div style="width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.3); border-top: 4px solid white; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 16px;"></div>
                <p style="margin: 0; font-size: 16px; font-weight: 500;">Connecting to AI Agent...</p>
            </div>
            
            <!-- Error State -->
            <div id="error-<?php echo $agent_id; ?>" style="display: none; padding: 20px; background: #f8d7da; color: #721c24; text-align: center; border-radius: 8px; margin: 20px;">
                <p style="margin: 0;">Failed to connect to AI Agent. Please try again later.</p>
            </div>
            
            <!-- D-ID SDK will create its professional interface here -->
            <div id="sdk-container-<?php echo $agent_id; ?>" style="display: none; width: 100%; height: 100%;">
                <!-- D-ID SDK professional interface will be injected here -->
            </div>
        </div>
        
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        
        <script>
        (async function() {
            const agentId = '<?php echo $agent_id; ?>';
            const container = document.getElementById('did-agent-proper-' + agentId);
            const loadingDiv = document.getElementById('loading-' + agentId);
            const errorDiv = document.getElementById('error-' + agentId);
            const sdkContainer = document.getElementById('sdk-container-' + agentId);
            
            try {
                console.log('🚀 Initializing D-ID Agent with proper SDK interface:', agentId);
                
                // Wait for D-ID SDK to load
                let retries = 0;
                while (typeof window.createAgentManager === 'undefined' && retries < 20) {
                    await new Promise(resolve => setTimeout(resolve, 250));
                    retries++;
                }
                
                if (typeof window.createAgentManager === 'undefined') {
                    throw new Error('D-ID SDK not loaded');
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
                
                // Create agent with D-ID SDK's professional interface
                console.log('🎨 Creating D-ID agent with proper SDK interface...');
                
                const auth = { type: 'key', clientKey: clientKey };
                
                // Define callbacks as per official documentation
                const callbacks = {
                    onSrcObjectReady: (value) => {
                        console.log('📹 Video stream ready - SDK will handle video element');
                        return value;
                    },
                    onVideoStateChange: (state) => {
                        console.log('📹 Video state:', state);
                        // SDK handles video switching automatically
                    },
                    onConnectionStateChange: (state) => {
                        console.log('🔗 Connection state:', state);
                        if (state === 'connected') {
                            console.log('✅ Agent connected successfully!');
                        }
                    },
                    onError: (error, errorData) => {
                        console.error('❌ Agent error:', error, errorData);
                        showError('Agent Error: ' + (error.description || error.message || JSON.stringify(error)));
                    },
                    onNewMessage: (messages, type) => {
                        console.log('💬 New message:', messages, type);
                        // SDK handles message display automatically
                    }
                };
                
                // Define stream options as per official documentation
                const streamOptions = {
                    compatibilityMode: 'auto',
                    streamWarmup: true,
                    // sessionTimeout: 300, // Optional
                    // outputResolution: 720 // Optional
                };
                
                // Create agent manager - D-ID SDK will create its professional interface
                const agentManager = await window.createAgentManager(agentId, {
                    auth,
                    callbacks,
                    streamOptions,
                    container: sdkContainer  // D-ID SDK will create its professional UI here
                });
                
                await agentManager.connect();
                
                // Hide loading and show SDK interface
                loadingDiv.style.display = 'none';
                sdkContainer.style.display = 'block';
                
                console.log('🎉 D-ID Agent with proper SDK interface is ready!');
                
                // Send test message
                setTimeout(() => {
                    agentManager.chat('Hello! Can you introduce yourself?');
                }, 2000);
                
            } catch (error) {
                console.error('❌ Failed to initialize agent:', error);
                loadingDiv.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.querySelector('p').textContent = error.message;
            }
            
            function showError(message) {
                loadingDiv.style.display = 'none';
                sdkContainer.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.querySelector('p').textContent = message;
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function add_admin_menu() {
        add_options_page(
            'D-ID Agent Proper Settings',
            'D-ID Agent Proper',
            'manage_options',
            'did-agent-proper',
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
            <h1>D-ID Agent Proper Settings</h1>
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
            <p>Use the shortcode <code>[did_agent_proper agent_id="your_agent_id"]</code> to display the D-ID agent with the official SDK interface.</p>
            
            <h3>Example</h3>
            <code>[did_agent_proper agent_id="v2_agt_aKkqeO6X" width="800px" height="600px"]</code>
            
            <h2>Features</h2>
            <ul>
                <li>✅ Uses official D-ID SDK interface</li>
                <li>✅ Professional UI components</li>
                <li>✅ Built-in video handling</li>
                <li>✅ Automatic message display</li>
                <li>✅ Proper WebRTC streaming</li>
            </ul>
        </div>
        <?php
    }
}

// Initialize the plugin
new DIDAgentProper();
?>
