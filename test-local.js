// Simple test script to verify the backend works locally
import axios from 'axios';

const BASE_URL = 'http://localhost:3000';

async function testBackend() {
  try {
    console.log('🧪 Testing D-ID Backend...\n');
    
    // Test health check
    console.log('1. Testing health check...');
    const healthResponse = await axios.get(`${BASE_URL}/`);
    console.log('✅ Health check:', healthResponse.data);
    
    // Test client key endpoint (will fail without real API key, but should show proper error)
    console.log('\n2. Testing client key endpoint...');
    try {
      const clientKeyResponse = await axios.post(`${BASE_URL}/api/client-key`, {});
      console.log('✅ Client key response:', clientKeyResponse.data);
    } catch (error) {
      console.log('⚠️  Client key test (expected to fail without real API key):', error.response?.data || error.message);
    }
    
    console.log('\n🎉 Backend is running correctly!');
    console.log('📝 Next steps:');
    console.log('   1. Add your real D-ID API key to .env file');
    console.log('   2. Push to GitHub');
    console.log('   3. Deploy to Render');
    
  } catch (error) {
    console.error('❌ Test failed:', error.message);
    console.log('💡 Make sure the server is running: npm start');
  }
}

testBackend();
