const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const pluginSlug = 'edt-performnce-optimizer';
const rootDir = path.resolve(__dirname, '..');
const distDir = path.resolve(rootDir, 'dist');
const stagingDir = path.resolve(distDir, pluginSlug);
const outputZip = path.resolve(rootDir, `${pluginSlug}.zip`);

console.log('--- Packaging EDT Performance Optimizer ---');

// 1. Clean dist directory
if (fs.existsSync(distDir)) {
  fs.rmSync(distDir, { recursive: true, force: true });
}
fs.mkdirSync(stagingDir, { recursive: true });

// 2. Define whitelist of files/folders to include in release
const includeItems = [
  'edt-performnce-optimizer.php',
  'uninstall.php',
  'index.php',
  'LICENSE.txt',
  'README.txt',
  'ASSET_RULES.json',
  'AUDIT.md',
  'COMPATIBILITY_MATRIX.md',
  'LIMITATIONS.md',
  'PLAN.md',
  'RESULTS.md',
  'ROLLOUT.md',
  'admin',
  'includes',
  'public',
  'languages'
];

function copyRecursive(src, dest) {
  const stat = fs.statSync(src);
  if (stat.isDirectory()) {
    fs.mkdirSync(dest, { recursive: true });
    for (const child of fs.readdirSync(src)) {
      copyRecursive(path.join(src, child), path.join(dest, child));
    }
  } else {
    fs.copyFileSync(src, dest);
  }
}

for (const item of includeItems) {
  const src = path.join(rootDir, item);
  const dest = path.join(stagingDir, item);
  if (fs.existsSync(src)) {
    copyRecursive(src, dest);
    console.log(`  + Added: ${item}`);
  } else {
    console.warn(`  ! Warning: ${item} not found`);
  }
}

// 3. Create zip using powershell Compress-Archive
console.log(`\nCompressing into ${outputZip}...`);
if (fs.existsSync(outputZip)) {
  fs.unlinkSync(outputZip);
}

const psCommand = `Compress-Archive -Path '${stagingDir}' -DestinationPath '${outputZip}' -Force`;
execSync(`powershell -NoProfile -Command "${psCommand}"`, { stdio: 'inherit' });

console.log(`\nVerifying zip file...`);
const tarList = execSync(`tar -tf "${outputZip}"`).toString();
const lines = tarList.split(/\r?\n/).filter(Boolean);
console.log(`  Total entries in zip: ${lines.length}`);

// Critical check for modules
const criticalModules = [
  'class-page-classifier.php',
  'class-compatibility-scanner.php',
  'class-conditional-asset-manager.php',
  'class-lcp-priority-manager.php',
  'class-diagnostics.php'
];

let allModulesFound = true;
for (const mod of criticalModules) {
  const found = lines.some(l => l.includes(mod));
  if (found) {
    console.log(`  ✔ Verified: ${mod} is in zip`);
  } else {
    console.error(`  ❌ ERROR: ${mod} MISSING from zip!`);
    allModulesFound = false;
  }
}

if (!allModulesFound) {
  process.exit(1);
}

// Also copy zip to parent directory and root directory for convenience
const parentZip = path.resolve(rootDir, '..', `${pluginSlug}.zip`);
const rootParentZip = path.resolve(rootDir, '..', '..', `${pluginSlug}.zip`);

fs.copyFileSync(outputZip, parentZip);
console.log(`  ✔ Copied zip to: ${parentZip}`);

fs.copyFileSync(outputZip, rootParentZip);
console.log(`  ✔ Copied zip to: ${rootParentZip}`);

console.log(`\n✅ Package built and verified successfully!`);
