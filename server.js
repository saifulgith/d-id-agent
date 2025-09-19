import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import axios from 'axios';
import { createProxyMiddleware } from 'http-proxy-middleware';
import https from 'https';

// Load environment variables
dotenv.config();

// Configure axios to ignore SSL certificate errors
const httpsAgent = new https.Agent({
  rejectUnauthorized: false
});

axios.defaults.httpsAgent = httpsAgent;

const app = express();
const PORT = process.env.PORT || 3000;

// CORS configuration - allow your WordPress domain
const FRONTEND_ORIGIN = process.env.FRONTEND_ORIGIN || '*';

// Custom CORS function to handle origin matching more flexibly
const corsOptions = {
  origin: function (origin, callback) {
    // Allow requests with no origin (like mobile apps or curl requests)
    if (!origin) return callback(null, true);
    
    // Allow exact match or with/without trailing slash
    const allowedOrigins = [
      'https://portdemy.com',
      'https://portdemy.com/',
      'http://localhost:3000',
      'http://localhost:8080'
    ];
    
    if (allowedOrigins.includes(origin)) {
      callback(null, true);
    } else {
      callback(new Error('Not allowed by CORS'));
    }
  },
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization', 'Accept'],
  optionsSuccessStatus: 200
};

app.use(cors(corsOptions));

// WebSocket proxy for D-ID notifications
app.use('/api/notifications', createProxyMiddleware({
  target: 'wss://notifications.d-id.com',
  changeOrigin: true,
  ws: true,
  secure: true,
  pathRewrite: { '^/api/notifications': '' },
  onError: (err, req, res) => {
    console.error('WebSocket proxy error:', err);
  }
}));

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// D-ID API configuration
const DID_API_BASE = 'https://api.d-id.com';
const DID_API_KEY = process.env.DID_API_KEY;

if (!DID_API_KEY) {
  console.error('❌ Missing DID_API_KEY environment variable');
  process.exit(1);
}

// Health check endpoint
app.get('/', (req, res) => {
  res.json({ 
    status: 'OK', 
    message: 'D-ID Agent Backend is running',
    timestamp: new Date().toISOString()
  });
});

// Get client key for frontend (secure server-side call) - both routes for compatibility
app.post('/api/client-key', async (req, res) => {
  try {
    console.log('🔑 Creating client key...');
    
    // Create a proper D-ID client key with WebSocket capabilities
    const requestBody = {
          allowed_domains: req.body.allowed_domains || [process.env.FRONTEND_ORIGIN || '*'],
      expires_in: req.body.expires_in || 3600,
      capabilities: ['streaming', 'ws'] // Enable WebSocket and streaming capabilities
    };
    
    console.log('📝 Request body:', JSON.stringify(requestBody, null, 2));
    
    // D-ID uses Basic Auth with email:apikey format
    const authString = Buffer.from(DID_API_KEY).toString('base64');
    
        const response = await axios.post(`${DID_API_BASE}/agents/client-key`, requestBody, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Basic ${authString}`
      }
    });

    console.log('✅ Client key created successfully');
    res.json(response.data);
    
  } catch (error) {
    console.error('❌ Error creating client key:', error.response?.data || error.message);
    console.error('❌ Full error details:', JSON.stringify(error.response?.data, null, 2));
    
    // Fallback to using server API key if D-ID client key creation fails
    console.log('🔄 Falling back to server API key...');
    console.log('⚠️  Note: Using server API key directly - this may not work with D-ID SDK');
    
    // The D-ID SDK expects a client key, but we'll use the server key
    // Format it as a proper client key that the SDK can use
    const tempClientKey = {
      client_key: DID_API_KEY,
      expires_in: 3600,
      allowed_domains: req.body.allowed_domains || [process.env.FRONTEND_ORIGIN || '*'],
      capabilities: ['streaming', 'ws'] // Add capabilities that the SDK expects
    };
    
    res.json(tempClientKey);
  }
});

// Also add the /client-key route that the frontend is calling
app.post('/client-key', async (req, res) => {
  try {
    console.log('🔑 Creating client key (via /client-key route)...');
    
    // Create a proper D-ID client key with WebSocket capabilities
    const requestBody = {
          allowed_domains: req.body.allowed_domains || [process.env.FRONTEND_ORIGIN || '*'],
      expires_in: req.body.expires_in || 3600,
      capabilities: ['streaming', 'ws'] // Enable WebSocket and streaming capabilities
    };
    
    console.log('📝 Request body:', JSON.stringify(requestBody, null, 2));
    
    // D-ID uses Basic Auth with email:apikey format
    const authString = Buffer.from(DID_API_KEY).toString('base64');
    
        const response = await axios.post(`${DID_API_BASE}/agents/client-key`, requestBody, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Basic ${authString}`
      }
    });

    console.log('✅ Client key created successfully');
    res.json(response.data);
    
  } catch (error) {
    console.error('❌ Error creating client key:', error.response?.data || error.message);
    console.error('❌ Full error details:', JSON.stringify(error.response?.data, null, 2));
    
    // Fallback to using server API key if D-ID client key creation fails
    console.log('🔄 Falling back to server API key...');
    console.log('⚠️  Note: Using server API key directly - this may not work with D-ID SDK');
    
    // The D-ID SDK expects a client key, but we'll use the server key
    // Format it as a proper client key that the SDK can use
    const tempClientKey = {
      client_key: DID_API_KEY,
      expires_in: 3600,
      allowed_domains: req.body.allowed_domains || [process.env.FRONTEND_ORIGIN || '*'],
      capabilities: ['streaming', 'ws'] // Add capabilities that the SDK expects
    };
    
    res.json(tempClientKey);
  }
});

// Proxy for D-ID agent operations
app.get('/api/agents/:agentId', async (req, res) => {
  try {
    const { agentId } = req.params;
    console.log(`🔍 Getting agent details for: ${agentId}`);
    
    // Use server API key for backend calls to D-ID
    const authString = Buffer.from(DID_API_KEY).toString('base64');
    
    console.log(`📡 Calling D-ID API: ${DID_API_BASE}/agents/${agentId}`);
    console.log(`🔑 Using auth: Basic ${authString.substring(0, 20)}...`);
    
    const response = await axios.get(`${DID_API_BASE}/agents/${agentId}`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Basic ${authString}`
      }
    });

    console.log('✅ Agent details retrieved successfully');
    res.json(response.data);
    
  } catch (error) {
    console.error('❌ Error getting agent:', error.response?.data || error.message);
    console.error('❌ Full error details:', JSON.stringify(error.response?.data, null, 2));
    res.status(error.response?.status || 500).json({
      error: 'Failed to get agent',
      details: error.response?.data || error.message
    });
  }
});

// Create a new agent
app.post('/api/agents', async (req, res) => {
  try {
    console.log('🤖 Creating new agent...');
    
    const authString = Buffer.from(DID_API_KEY).toString('base64');
    
    const response = await axios.post(`${DID_API_BASE}/agents`, req.body, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Basic ${authString}`
      }
    });

    console.log('✅ Agent created successfully');
    res.json(response.data);
    
  } catch (error) {
    console.error('❌ Error creating agent:', error.response?.data || error.message);
    res.status(error.response?.status || 500).json({
      error: 'Failed to create agent',
      details: error.response?.data || error.message
    });
  }
});

// Proxy for D-ID stream operations
app.post('/api/agents/:agentId/streams', async (req, res) => {
  try {
    const { agentId } = req.params;
    console.log(`🎥 Creating stream for agent: ${agentId}`);
    
    const authString = Buffer.from(DID_API_KEY).toString('base64');
    
    const response = await axios.post(`${DID_API_BASE}/agents/${agentId}/streams`, req.body, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Basic ${authString}`
      }
    });

    console.log('✅ Stream created successfully');
    res.json(response.data);
    
  } catch (error) {
    console.error('❌ Error creating stream:', error.response?.data || error.message);
    res.status(error.response?.status || 500).json({
      error: 'Failed to create stream',
      details: error.response?.data || error.message
    });
  }
});

app.get('/api/agents/:agentId/streams', async (req, res) => {
  try {
    const { agentId } = req.params;
    console.log(`📋 Getting streams for agent: ${agentId}`);
    
    const authString = Buffer.from(DID_API_KEY).toString('base64');
    
    const response = await axios.get(`${DID_API_BASE}/agents/${agentId}/streams`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Basic ${authString}`
      }
    });

    res.json(response.data);
    
  } catch (error) {
    console.error('❌ Error getting streams:', error.response?.data || error.message);
    res.status(error.response?.status || 500).json({
      error: 'Failed to get streams',
      details: error.response?.data || error.message
    });
  }
});

// Chat endpoint for agent conversations
app.post('/api/agents/:agentId/chat', async (req, res) => {
  try {
    const { agentId } = req.params;
    console.log(`💬 Creating chat for agent: ${agentId}`);

    const authString = Buffer.from(DID_API_KEY).toString('base64');

    const response = await axios.post(`${DID_API_BASE}/agents/${agentId}/chat`, req.body, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Basic ${authString}`
      }
    });

    console.log('✅ Chat created successfully');
    res.json(response.data);

  } catch (error) {
    console.error('❌ Error creating chat:', error.response?.data || error.message);
    res.status(error.response?.status || 500).json({
      error: 'Failed to create chat',
      details: error.response?.data || error.message
    });
  }
});

// Handle OPTIONS requests for SDP (CORS preflight)
app.options('/api/agents/:agentId/streams/:streamId/sdp', (req, res) => {
  res.set({
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'POST, GET, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization, Client-Key',
    'Access-Control-Max-Age': '86400'
  });
  res.sendStatus(200);
});

// WebRTC SDP endpoint for stream connections (MUST be before catch-all proxy)
app.post('/api/agents/:agentId/streams/:streamId/sdp', async (req, res) => {
  try {
    const { agentId, streamId } = req.params;
    console.log(`🎥 SDP exchange for agent: ${agentId}, stream: ${streamId}`);
    console.log(`🎥 SDP request body:`, JSON.stringify(req.body, null, 2));
    
    // Use server API key for SDP - D-ID may not support client keys for WebRTC operations
    const authString = Buffer.from(DID_API_KEY).toString('base64');
    const authHeader = `Basic ${authString}`;
    console.log(`🔑 Using server API key for SDP`);
    
    const response = await axios.post(`${DID_API_BASE}/agents/${agentId}/streams/${streamId}/sdp`, req.body, {
      headers: {
        'Accept': 'application/sdp',
        'Content-Type': 'application/sdp',
        'Authorization': authHeader
      }
    });

    console.log('✅ SDP exchange successful');
    console.log('✅ SDP response type:', typeof response.data);
    console.log('✅ SDP response length:', response.data ? response.data.length : 'null');

    // Set proper CORS headers for WebRTC
    res.set({
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'POST, GET, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization, Client-Key',
      'Content-Type': 'application/sdp'
    });

    // Send SDP data as text, not JSON
    res.send(response.data);
    
  } catch (error) {
    console.error('❌ Error with SDP exchange:', error.response?.data || error.message);
    console.error('❌ SDP error status:', error.response?.status);
    console.error('❌ SDP error headers:', error.response?.headers);
    
    res.set({
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'POST, GET, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization, Client-Key',
      'Content-Type': 'application/sdp'
    });

    res.status(error.response?.status || 500).send(error.response?.data || error.message);
  }
});

// Handle OPTIONS requests for ICE (CORS preflight)
app.options('/api/agents/:agentId/streams/:streamId/ice', (req, res) => {
  res.set({
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'POST, GET, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization, Client-Key',
    'Access-Control-Max-Age': '86400'
  });
  res.sendStatus(200);
});

// WebRTC ICE candidate endpoint (MUST be before catch-all proxy)
app.post('/api/agents/:agentId/streams/:streamId/ice', async (req, res) => {
  try {
    const { agentId, streamId } = req.params;
    console.log(`🧊 ICE candidate for agent: ${agentId}, stream: ${streamId}`);
    console.log(`🧊 ICE request body:`, JSON.stringify(req.body, null, 2));
    
    // Use server API key for ICE - D-ID may not support client keys for WebRTC operations
    const authString = Buffer.from(DID_API_KEY).toString('base64');
    const authHeader = `Basic ${authString}`;
    console.log(`🔑 Using server API key for ICE`);
    
    const response = await axios.post(`${DID_API_BASE}/agents/${agentId}/streams/${streamId}/ice`, req.body, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': authHeader
      }
    });

    console.log('✅ ICE candidate processed successfully');
    console.log('✅ ICE response:', JSON.stringify(response.data, null, 2));
    
    // Set proper CORS headers for WebRTC
    res.set({
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'POST, GET, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization, Client-Key',
      'Content-Type': 'application/json'
    });
    
    res.json(response.data);
    
  } catch (error) {
    console.error('❌ Error with ICE candidate:', error.response?.data || error.message);
    console.error('❌ ICE error status:', error.response?.status);
    console.error('❌ ICE error headers:', error.response?.headers);
    
    res.set({
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'POST, GET, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization, Client-Key',
      'Content-Type': 'application/json'
    });
    
    res.status(error.response?.status || 500).json({
      error: 'Failed ICE candidate',
      details: error.response?.data || error.message
    });
  }
});

// Catch-all proxy for all agent sub-routes (chat sessions, streams, etc.)
// BUT exclude ICE and SDP routes which are handled above
app.use('/api/agents/:agentId/*', async (req, res) => {
  try {
    const { agentId } = req.params;
    const subPath = req.params[0]; // Everything after /api/agents/:agentId/
    
    // Skip ICE and SDP routes - they're handled by specific endpoints above
    if (subPath.includes('/ice') || subPath.includes('/sdp')) {
      console.log(`⚠️ Skipping ${subPath} - handled by specific endpoint`);
      return;
    }
    
    const targetUrl = `${DID_API_BASE}/agents/${agentId}/${subPath}`;
    
    console.log(`🔄 Proxying agent sub-route: ${req.method} ${req.originalUrl} -> ${targetUrl}`);
    console.log(`🔑 Original Authorization header:`, req.headers.authorization);
    console.log(`🔑 Request body:`, JSON.stringify(req.body, null, 2));

    // Use server API key for ALL operations - client keys seem to have issues with chat sessions
    // DID_API_KEY is already in format "email:apikey", so encode it directly
    const authString = Buffer.from(DID_API_KEY).toString('base64');
    const authHeader = `Basic ${authString}`;
    console.log(`🔑 Using server API key for all routes: ${subPath}`);
    
    // Prepare headers - keep original headers but ensure proper auth
    const headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': authHeader
    };
    
    const response = await axios({
      method: req.method,
      url: targetUrl,
      data: req.body,
      headers: headers,
      params: req.query
    });

    console.log(`✅ Proxied successfully: ${req.method} ${req.originalUrl} -> ${response.status}`);
    res.json(response.data);

  } catch (error) {
    console.error(`❌ Proxy error for ${req.method} ${req.originalUrl}:`, error.response?.data || error.message);
    console.error(`❌ Error status:`, error.response?.status);
    console.error(`❌ Error headers:`, error.response?.headers);
    res.status(error.response?.status || 500).json({
      error: 'Proxy request failed',
      details: error.response?.data || error.message
    });
  }
});


// Error handling middleware
app.use((err, req, res, next) => {
  console.error('❌ Server error:', err);
  res.status(500).json({
    error: 'Internal server error',
    message: err.message
  });
});

// 404 handler
app.use('*', (req, res) => {
  res.status(404).json({
    error: 'Not found',
    message: `Route ${req.originalUrl} not found`
  });
});

// Start server
app.listen(PORT, () => {
  console.log(`🚀 D-ID Agent Backend running on port ${PORT}`);
  console.log(`🌐 Health check: http://localhost:${PORT}`);
  console.log(`🔑 D-ID API Key: ${DID_API_KEY ? '✅ Set' : '❌ Missing'}`);
  console.log(`🌍 Frontend Origin: ${FRONTEND_ORIGIN}`);
});
