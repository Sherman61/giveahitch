import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const projectRoot = resolve(__dirname, '..');

function run(command, args, options = {}) {
  return new Promise((resolvePromise, rejectPromise) => {
    console.log(`\n$ ${[command, ...args].join(' ')}`);
    const child = spawn(command, args, {
      stdio: 'inherit',
      cwd: projectRoot,
      shell: process.platform === 'win32',
      ...options,
    });

    child.on('exit', (code) => {
      if (code === 0) {
        resolvePromise(undefined);
      } else {
        rejectPromise(new Error(`${command} exited with code ${code}`));
      }
    });
  });
}

async function buildAndroid() {
  const gradleCmd = process.platform === 'win32' ? '.\\gradlew.bat' : './gradlew';
  await run('npx', ['expo', 'prebuild', '--platform', 'android', '--clean']);
  await run(gradleCmd, ['assembleRelease'], { cwd: resolve(projectRoot, 'android') });
  console.log(
    '\n✅ APK ready at android/app/build/outputs/apk/release/app-release-unsigned.apk (sign before shipping).',
  );
}

buildAndroid().catch((error) => {
  console.error('Build failed:', error.message);
  process.exit(1);
});
