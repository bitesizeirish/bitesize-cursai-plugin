#!/usr/bin/env node

/**
 * ZIP creation script for Bitesize Cúrsaí Plugin
 * Replaces gulp zip functionality
 */

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

function createZip() {
    try {
        // Read version from package.json
        const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
        const version = packageJson.version;
        
        // Create dist directory
        const distDir = path.join(__dirname, '..', 'dist');
        if (!fs.existsSync(distDir)) {
            fs.mkdirSync(distDir, { recursive: true });
        }
        
        const zipPath = path.join(distDir, `bitesize-cursai-plugin-v${version}.zip`);
        
        console.log(`📦 Creating deployment ZIP: ${path.basename(zipPath)}`);
        
        // Create write stream
        const output = fs.createWriteStream(zipPath);
        const archive = archiver('zip', { zlib: { level: 9 } });
        
        output.on('close', () => {
            const sizeMB = (archive.pointer() / 1024 / 1024).toFixed(2);
            console.log(`✅ ZIP created successfully!`);
            console.log(`📁 File: ${zipPath}`);
            console.log(`📏 Size: ${sizeMB} MB`);
            console.log('🚀 Ready for deployment!');
        });
        
        archive.on('error', (err) => {
            throw err;
        });
        
        archive.pipe(output);
        
        // CRITICAL: Use consistent plugin directory name for WordPress
        // This ensures WordPress updates the existing plugin instead of creating new ones
        const pluginDirName = 'bitesize-cursai-plugin';
        
        console.log(`📁 Plugin directory name: ${pluginDirName} (consistent across versions)`);
        
        // Add files to archive (exclude dev files)
        const excludePatterns = [
            'node_modules/**',
            '.git/**',
            'tests/**',
            'build/**',
            'dist/**',
            'assets/scss/**',
            'assets/js/src/**',
            'scripts/**',
            '.githooks/**',
            'gulpfile.js',
            'package*.json',
            'composer.lock',
            'phpunit.xml',
            'deploy.sh',
            'setup*.sh',
            '*.md',
            '.env*',
            'env.example'
        ];
        
        // Add all files except excluded ones, placing them in consistent plugin directory
        archive.glob('**/*', {
            ignore: excludePatterns,
            dot: false
        }, { prefix: pluginDirName + '/' });
        
        // Finalize the archive
        archive.finalize();
        
    } catch (error) {
        console.error('❌ Error creating ZIP:', error.message);
        process.exit(1);
    }
}

createZip();

