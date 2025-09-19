import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import axios from 'axios';

// Load environment variables
dotenv.config();

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
    
    // Create a proper D-ID client key
    const requestBody = {
      allowed_origins: req.body.allowed_origins || [process.env.FRONTEND_ORIGIN || '*'],
      expires_in: req.body.expires_in || 3600
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
    const tempClientKey = {
      client_key: DID_API_KEY,
      expires_in: 3600,
      allowed_origins: req.body.allowed_origins || [process.env.FRONTEND_ORIGIN || '*']
    };
    
    res.json(tempClientKey);
  }
});

// Also add the /client-key route that the frontend is calling
app.post('/client-key', async (req, res) => {
  try {
    console.log('🔑 Creating client key (via /client-key route)...');
    
    // Create a proper D-ID client key
    const requestBody = {
      allowed_origins: req.body.allowed_origins || [process.env.FRONTEND_ORIGIN || '*'],
      expires_in: req.body.expires_in || 3600
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
    const tempClientKey = {
      client_key: DID_API_KEY,
      expires_in: 3600,
      allowed_origins: req.body.allowed_origins || [process.env.FRONTEND_ORIGIN || '*']
    };
    
    res.json(tempClientKey);
  }
});

// Proxy for D-ID agent operations
app.get('/api/agents/:agentId', async (req, res) => {
  try {
    const { agentId } = req.params;
    console.log(`🔍 Getting agent details for: ${agentId}`);
    
    const authString = Buffer.from(DID_API_KEY).toString('base64');
    
    const response = await axios.get(`${DID_API_BASE}/agents/${agentId}`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Basic ${authString}`
      }
    });

    res.json(response.data);
    
  } catch (error) {
    console.error('❌ Error getting agent:', error.response?.data || error.message);
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
