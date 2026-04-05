const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

async function createZip(inputFolder, outputZipPath) {
	return new Promise((resolve, reject) => {
		const output = fs.createWriteStream(outputZipPath);
		const archive = archiver('zip', { zlib: { level: 9 } });

		output.on('close', () => {
			console.log(`📦 ZIP created: ${outputZipPath}`);
			console.log(`📊 Size: ${archive.pointer()} bytes`);
			resolve();
		});

		archive.on('error', reject);

		archive.pipe(output);
		archive.directory(inputFolder, false);
		archive.finalize();
	});
}

// CLI usage
const inputFolder = path.resolve(process.argv[2]);
const outputZipPath = path.resolve(process.argv[3]);

if (!inputFolder || !outputZipPath) {
	console.error('❌ Usage: node zip.js <inputFolder> <outputZipPath>');
	process.exit(1);
}

const outputDir = path.dirname(outputZipPath);
if (!fs.existsSync(outputDir)) {
	fs.mkdirSync(outputDir, { recursive: true });
}

createZip(inputFolder, outputZipPath)
	.then(() => console.log('✅ Done!'))
	.catch(err => {
		console.error('❌ Error:', err.message);
		process.exit(1);
	});