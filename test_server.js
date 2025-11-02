const http = require('http');
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');

const PORT = 3000;
const ROOT_DIR = __dirname;

// MIME types
const mimeTypes = {
    '.html': 'text/html',
    '.css': 'text/css',
    '.js': 'application/javascript',
    '.json': 'application/json',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.gif': 'image/gif',
    '.php': 'text/plain'
};

const server = http.createServer((req, res) => {
    console.log(`${new Date().toISOString()} - ${req.method} ${req.url}`);
    
    // Handle PHP files by executing them (basic simulation)
    if (req.url.endsWith('.php')) {
        // For now, just return an error message indicating PHP needs proper server
        res.writeHead(200, {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization'
        });
        res.end(JSON.stringify({
            error: 'PHP execution requires proper PHP server (Apache/Nginx with PHP). This is Node.js server for testing HTML/JS only.'
        }));
        return;
    }
    
    // Handle CORS preflight
    if (req.method === 'OPTIONS') {
        res.writeHead(200, {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization'
        });
        res.end();
        return;
    }
    
    // Serve static files
    let filePath = path.join(ROOT_DIR, req.url === '/' ? 'test_payment.html' : req.url);
    
    // Security check
    if (!filePath.startsWith(ROOT_DIR)) {
        res.writeHead(403);
        res.end('Access denied');
        return;
    }
    
    fs.readFile(filePath, (err, content) => {
        if (err) {
            if (err.code === 'ENOENT') {
                res.writeHead(404);
                res.end('File not found');
            } else {
                res.writeHead(500);
                res.end('Server error');
            }
            return;
        }
        
        const ext = path.extname(filePath).toLowerCase();
        const mimeType = mimeTypes[ext] || 'application/octet-stream';
        
        res.writeHead(200, {
            'Content-Type': mimeType,
            'Access-Control-Allow-Origin': '*'
        });
        res.end(content);
    });
});

server.listen(PORT, () => {
    console.log(`Test server running at http://localhost:${PORT}`);
    console.log('Note: This server cannot execute PHP files. You need a proper LAMP stack for PHP execution.');
});