const fs = require('fs');
const path = require('path');

module.exports = () => {
  const projectRoot = __dirname;
  const googleServicesFile =
    process.env.EXPO_GOOGLE_SERVICES_FILE || './google-services.json';
  const googleServicesPath = path.resolve(projectRoot, googleServicesFile);
  const hasGoogleServicesFile = fs.existsSync(googleServicesPath);

  return {
    expo: {
      name: 'GlitchaHitch',
      slug: 'glitchahitch',
      scheme: 'glitchahitch',
      version: '0.1.0',
      orientation: 'portrait',
      icon: './assets/icon.png',
      userInterfaceStyle: 'automatic',
      splash: {
        image: './assets/splash.png',
        resizeMode: 'contain',
        backgroundColor: '#f5f5f5',
      },
      assetBundlePatterns: ['**/*'],
      ios: {
        supportsTablet: true,
        bundleIdentifier: 'com.glitchahitch.app',
        useFrameworks: 'static',
      },
      android: {
        package: 'com.glitchahitch.app',
        adaptiveIcon: {
          foregroundImage: './assets/adaptive-icon.png',
          backgroundColor: '#ffffff',
        },
        ...(hasGoogleServicesFile ? { googleServicesFile } : {}),
      },
      plugins: ['expo-notifications', 'expo-dev-client'],
      extra: {
        eas: {
          projectId: '00000000-0000-0000-0000-000000000000',
        },
        apiUrl: 'https://glitchahitch.com/api',
        googleServicesFileConfigured: hasGoogleServicesFile,
      },
    },
  };
};
