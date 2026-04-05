const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const readline = require('readline');

// -------------------- HELPERS --------------------
function exec(cmd, silent = false) {
	return execSync(cmd, {
		stdio: silent ? 'pipe' : 'inherit',
		encoding: 'utf8'
	});
}

function bumpPatch(version) {
	const parts = version.replace(/^v/, '').split('.').map(Number);
	if (parts.length !== 3 || parts.some(isNaN)) {
		console.error(`❌ Cannot bump invalid version: "${version}"`);
		process.exit(1);
	}
	parts[2] += 1;
	return parts.join('.');
}

function releaseExists(version) {
	try {
		exec(`gh release view v${version}`, true);
		return true;
	} catch {
		return false;
	}
}

function ask(rl, question) {
	return new Promise(resolve => rl.question(question, resolve));
}

function isYes(answer) {
	return ['y', 'yes'].includes(answer.trim().toLowerCase());
}

// -------------------- DIST CLEANUP --------------------
const distPath = path.join(__dirname, '../nativephp/electron/dist');

function cleanDist() {
	if (!fs.existsSync(distPath)) {
		console.log('   ℹ️  dist folder does not exist yet — nothing to clean.');
		return;
	}

	const entries = fs.readdirSync(distPath);
	if (entries.length === 0) {
		console.log('   ℹ️  dist folder is already empty.');
		return;
	}

	console.log(`   🗑  Removing ${entries.length} item(s) from dist...`);
	fs.rmSync(distPath, { recursive: true, force: true });
	fs.mkdirSync(distPath, { recursive: true });
	console.log('   ✔ dist cleaned.');
}

// -------------------- LOAD & PARSE VERSION FROM .ENV --------------------
const envPath = path.join(__dirname, '../.env');
let envContent = fs.readFileSync(envPath, 'utf8');

const versionLineRegex = /^(APP_VERSION=)(.+)$/m;
const match = envContent.match(versionLineRegex);

if (!match) {
	console.error('❌ APP_VERSION not found in .env');
	process.exit(1);
}

let version = match[2].replace(/['"]/g, '').trim();
const semverExtract = version.match(/^(\d+\.\d+\.\d+)/);
if (!semverExtract) {
	console.error(`❌ Could not parse a valid semver from APP_VERSION: "${match[2]}"`);
	process.exit(1);
}
version = semverExtract[1];
console.log(`\n🔍 Current version in .env: ${version}`);

// -------------------- ENV WRITERS --------------------
function writeEnvKey(key, value, quoted = true) {
	const regex = new RegExp(`^(${key}=)(.+)$`, 'm');
	const newLine = quoted ? `$1"${value}"` : `$1${value}`;
	if (regex.test(envContent)) {
		envContent = envContent.replace(regex, newLine);
	} else {
		envContent += `\n${key}=${quoted ? `"${value}"` : value}`;
	}
	fs.writeFileSync(envPath, envContent, 'utf8');
}

function writeVersion(v) {
	writeEnvKey('APP_VERSION', v);
}

function setEnvToProduction() {
	envContent = envContent
		.replace(/^(APP_ENV=)(.+)$/m, '$1production')
		.replace(/^(APP_DEBUG=)(.+)$/m, '$1false');
	fs.writeFileSync(envPath, envContent, 'utf8');
	console.log('   ✔ APP_ENV=production, APP_DEBUG=false');
}

function setEnvToLocal() {
	envContent = envContent
		.replace(/^(APP_ENV=)(.+)$/m, '$1local')
		.replace(/^(APP_DEBUG=)(.+)$/m, '$1true');
	fs.writeFileSync(envPath, envContent, 'utf8');
	console.log('   ✔ APP_ENV=local, APP_DEBUG=true (restored)');
}

// -------------------- CHANGELOG --------------------
const changelogPath = path.join(__dirname, '../public/changelog.json');

function loadChangelog() {
	if (!fs.existsSync(changelogPath)) return [];
	try {
		return JSON.parse(fs.readFileSync(changelogPath, 'utf8'));
	} catch {
		return [];
	}
}

async function buildChangelog(rl, v) {
	console.log('\n📝 Enter changelog entries for this release.');
	console.log('   Type each item and press Enter. Leave blank and press Enter when done.\n');

	const entries = [];
	let i = 1;
	while (true) {
		const line = (await ask(rl, `   ${i}. `)).trim();
		if (!line) break;
		entries.push(line);
		i++;
	}

	if (entries.length === 0) {
		console.log('   ℹ️  No entries added — changelog skipped for this version.');
		return;
	}

	const changelog = loadChangelog();
	const filtered = changelog.filter(e => e.version !== v);
	filtered.unshift({
		version: v,
		date: new Date().toISOString().split('T')[0],
		changes: entries
	});

	fs.writeFileSync(changelogPath, JSON.stringify(filtered, null, 2), 'utf8');
	console.log(`   ✔ Changelog saved → public/changelog.json (${entries.length} item${entries.length !== 1 ? 's' : ''})`);
}

// -------------------- MAIN --------------------
(async () => {
	const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
	let zipCreated = false;
	let zipPath = null;
	let wentToProduction = false;

	process.on('exit', () => rl.close());

	// -------------------- STEP 1: VERSION CHECK --------------------
	if (releaseExists(version)) {
		const suggested = bumpPatch(version);
		console.log(`\n⚠️  GitHub release v${version} already exists.`);
		console.log(`   Suggested next version: ${suggested}\n`);
		console.log(`   [1] Use suggested version (${suggested})`);
		console.log(`   [2] Enter a custom version`);
		console.log(`   [3] Cancel and exit\n`);

		const choice = (await ask(rl, '👉 Choose an option [1/2/3]: ')).trim();

		if (choice === '1') {
			version = suggested;
		} else if (choice === '2') {
			const custom = (await ask(rl, '✏️  Enter new version (e.g. 1.2.0): ')).trim().replace(/^v/, '');
			if (!/^\d+\.\d+\.\d+$/.test(custom)) {
				console.error('❌ Invalid version format. Use semver: X.Y.Z');
				process.exit(1);
			}
			if (releaseExists(custom)) {
				console.error(`❌ Release v${custom} also already exists. Aborting.`);
				process.exit(1);
			}
			version = custom;
		} else {
			console.log('🚫 Cancelled.');
			process.exit(0);
		}
	}

	// -------------------- STEP 2: WRITE VERSION --------------------
	console.log(`\n✅ Using version: ${version}`);
	writeVersion(version);
	console.log(`   ✔ .env updated → APP_VERSION="${version}"`);

	// -------------------- STEP 3: CHANGELOG --------------------
	const doChangelog = isYes(await ask(rl, '\n📝 Write a changelog for this release? [y/N]: '));
	if (doChangelog) {
		await buildChangelog(rl, version);
	}

	// -------------------- STEP 4: CLEAN PREVIOUS DIST --------------------
	console.log('\n🧹 Previous build:');
	const distEntries = fs.existsSync(distPath) ? fs.readdirSync(distPath) : [];

	if (distEntries.length > 0) {
		console.log(`   Found ${distEntries.length} item(s) in dist:\n`);
		distEntries.forEach(f => console.log(`      • ${f}`));
		console.log();
		const doClean = isYes(await ask(rl, '   Delete previous build before continuing? [y/N]: '));
		if (doClean) {
			cleanDist();
		} else {
			console.log('   ⚠️  Keeping existing dist — old files may conflict with new build.');
		}
	} else {
		console.log('   dist is empty or does not exist — nothing to clean.');
	}

	// -------------------- STEP 5: SWITCH TO PRODUCTION (OPTIONAL) --------------------
	console.log('\n⚙️  Environment:');
	const currentEnv = (envContent.match(/^APP_ENV=(.+)$/m) || [])[1]?.trim() || 'unknown';
	console.log(`   Current APP_ENV: ${currentEnv}`);

	const goProduction = isYes(await ask(rl, '   Switch .env to production before building? [y/N]: '));
	if (goProduction) {
		setEnvToProduction();
		wentToProduction = true;
	}

	// -------------------- STEP 6: BUILD --------------------
	console.log('\n🔨 Running build...\n');
	try {
		exec('npm run build');
		exec('php artisan optimize');
		exec('php artisan native:build');
		console.log('\n✅ Build complete.\n');
	} catch (err) {
		console.error('❌ Build failed:', err.message);
		if (wentToProduction) {
			console.log('\n↩️  Restoring .env to local after failed build...');
			setEnvToLocal();
		}
		process.exit(1);
	}

	// -------------------- STEP 7: RESTORE ENV --------------------
	if (wentToProduction) {
		console.log('\n↩️  Restoring .env to local...');
		setEnvToLocal();
	}

	// -------------------- STEP 8: LOCATE EXE --------------------
	const exePath = path.join(
		__dirname,
		`../nativephp/electron/dist/welfare-app-${version}-setup.exe`
	);

	if (!fs.existsSync(exePath)) {
		console.error(`❌ EXE not found: ${exePath}`);
		console.error('👉 Check that the NativePHP build completed successfully.');
		process.exit(1);
	}

	// -------------------- STEP 9: OPTIONAL ZIP --------------------
	zipPath = path.join(__dirname, `../releases/welfare-app-${version}.zip`);
	const doZip = isYes(await ask(rl, '📦 Create a ZIP of the build? [y/N]: '));
	let uploadArgs = `"${exePath}"`;

	if (doZip) {
		const releasesDir = path.dirname(zipPath);
		if (!fs.existsSync(releasesDir)) fs.mkdirSync(releasesDir, { recursive: true });

		console.log('📦 Creating ZIP...');
		exec(`node scripts/zip.cjs "${path.dirname(exePath)}" "${zipPath}"`);
		zipCreated = true;
		console.log(`   ✔ ZIP created: ${zipPath}`);
		uploadArgs += ` "${zipPath}"`;
	}

	// -------------------- STEP 10: PUBLISH RELEASE --------------------
	const doPublish = isYes(await ask(rl, `\n🚀 Publish GitHub release v${version}? [y/N]: `));

	if (!doPublish) {
		console.log('\n🚫 Publish skipped. Build is ready in /dist.');
		if (zipCreated && fs.existsSync(zipPath)) {
			fs.unlinkSync(zipPath);
			console.log(`   🗑  ZIP removed (not needed without publish).`);
		}
		process.exit(0);
	}

	try {
		console.log(`\n🚀 Creating GitHub release v${version}...`);

		const cmd = [
			`gh release create v${version}`,
			`--generate-notes`,
			`--title "Release v${version}"`,
			uploadArgs
		].join(' ');

		exec(cmd);
		console.log('\n🎉 Release published successfully!');
	} catch (err) {
		console.error('❌ Release failed:', err.message);
		if (zipCreated && fs.existsSync(zipPath)) {
			fs.unlinkSync(zipPath);
			console.log(`   🗑  ZIP cleaned up after failed release.`);
		}
		process.exit(1);
	}

	// -------------------- STEP 11: CLEANUP ZIP --------------------
	if (zipCreated && fs.existsSync(zipPath)) {
		fs.unlinkSync(zipPath);
		console.log(`   🗑  ZIP deleted after successful upload.`);
	}

	console.log('\n✅ All done! 🚀\n');
	process.exit(0);
})();
