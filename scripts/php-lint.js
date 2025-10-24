#!/usr/bin/env node

/**
 * PHP Lint Checker for Bitesize Cúrsaí Plugin
 * Ensures NO PHP errors reach production
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function findPhpFiles(dir) {
    const phpFiles = [];
    
    function searchDir(currentDir) {
        const items = fs.readdirSync(currentDir);
        
        for (const item of items) {
            const fullPath = path.join(currentDir, item);
            const stat = fs.statSync(fullPath);
            
            if (stat.isDirectory()) {
                // Skip vendor, node_modules, and build directories
                if (!['vendor', 'node_modules', 'build', 'dist', '.git'].includes(item)) {
                    searchDir(fullPath);
                }
            } else if (item.endsWith('.php')) {
                phpFiles.push(fullPath);
            }
        }
    }
    
    searchDir(dir);
    return phpFiles;
}

function lintPhpFile(filePath) {
    try {
        console.log(`🔍 Checking: ${filePath}`);
        execSync(`php -l "${filePath}"`, { stdio: 'pipe' });
        return { success: true, file: filePath };
    } catch (error) {
        return { 
            success: false, 
            file: filePath, 
            error: error.stdout.toString() + error.stderr.toString() 
        };
    }
}

function main() {
    console.log('🚨 CRITICAL: Running PHP Syntax Check for Production Safety');
    console.log('=' .repeat(60));
    
    const startTime = Date.now();
    const rootDir = path.join(__dirname, '..');
    const phpFiles = findPhpFiles(rootDir);
    
    console.log(`📁 Found ${phpFiles.length} PHP files to check`);
    console.log('');
    
    const errors = [];
    let checkedCount = 0;
    
    for (const file of phpFiles) {
        const result = lintPhpFile(file);
        checkedCount++;
        
        if (result.success) {
            console.log(`✅ ${path.relative(rootDir, file)}`);
        } else {
            console.log(`❌ ${path.relative(rootDir, file)}`);
            console.log(`   Error: ${result.error.trim()}`);
            errors.push(result);
        }
    }
    
    const endTime = Date.now();
    console.log('');
    console.log('=' .repeat(60));
    
    if (errors.length === 0) {
        console.log(`✅ SUCCESS: All ${checkedCount} PHP files passed syntax check!`);
        console.log(`⏱️  Time: ${endTime - startTime}ms`);
        console.log('🚀 Safe for production deployment');
        process.exit(0);
    } else {
        console.log(`❌ FAILURE: ${errors.length} PHP files have syntax errors!`);
        console.log('');
        console.log('🚨 DEPLOYMENT BLOCKED - Fix these errors before production:');
        console.log('');
        
        errors.forEach((error, index) => {
            console.log(`${index + 1}. ${path.relative(rootDir, error.file)}`);
            console.log(`   ${error.error.trim()}`);
            console.log('');
        });
        
        console.log('🛑 CRITICAL: Cannot deploy with PHP syntax errors!');
        console.log('💼 Business site protection: Deployment halted');
        process.exit(1);
    }
}

main();

