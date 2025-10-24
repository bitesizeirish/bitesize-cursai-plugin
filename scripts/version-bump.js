#!/usr/bin/env node

/**
 * Version bump script for Bitesize Cúrsaí Plugin
 * Replaces gulp version functionality
 */

const fs = require('fs');
const path = require('path');
const semver = require('semver');

function updateVersion() {
    try {
        // Read package.json
        const packagePath = path.join(__dirname, '..', 'package.json');
        const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
        
        // Increment patch version
        const currentVersion = packageJson.version;
        const newVersion = semver.inc(currentVersion, 'patch');
        
        console.log(`📦 Bumping version: ${currentVersion} → ${newVersion}`);
        
        // Update package.json
        packageJson.version = newVersion;
        fs.writeFileSync(packagePath, JSON.stringify(packageJson, null, 2) + '\n');
        
        // Update main plugin file
        const pluginPath = path.join(__dirname, '..', 'bitesize-cursai-plugin.php');
        let pluginContent = fs.readFileSync(pluginPath, 'utf8');
        
        // Update Version header
        pluginContent = pluginContent.replace(
            /Version: [\d.]+/,
            `Version: ${newVersion}`
        );
        
        // Update constant
        pluginContent = pluginContent.replace(
            /define\('BITESIZE_CURSAI_VERSION', '[^']+'\)/,
            `define('BITESIZE_CURSAI_VERSION', '${newVersion}')`
        );
        
        fs.writeFileSync(pluginPath, pluginContent);
        
        // Do NOT modify composer.json version for VCS projects
        
        console.log(`✅ Version updated to ${newVersion}`);
        console.log('📁 Updated files:');
        console.log('   - package.json');
        console.log('   - bitesize-cursai-plugin.php');
        console.log('   - composer.json (unchanged)');
        
    } catch (error) {
        console.error('❌ Error updating version:', error.message);
        process.exit(1);
    }
}

updateVersion();

