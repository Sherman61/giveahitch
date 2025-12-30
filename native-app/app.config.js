const fs = require("fs");
const path = require("path");

const uuidPattern =
  /^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/;

function resolveProjectId() {
  const candidate =
    process.env.EXPO_PUBLIC_PROJECT_ID ||
    "ed67a7c8-85af-457c-a459-f40ddcdd31b7";

  if (!candidate) {
    return null;
  }

  if (!uuidPattern.test(candidate)) {
    console.warn(
      `[expo-config] Ignoring invalid Expo project ID "${candidate}". Expected a UUID.`
    );
    return null;
  }

  return candidate;
}

module.exports = () => {
  const projectRoot = __dirname;
  const googleServicesFile =
    process.env.EXPO_GOOGLE_SERVICES_FILE || "./google-services.json";
  const googleServicesPath = path.resolve(projectRoot, googleServicesFile);
  const hasGoogleServicesFile = fs.existsSync(googleServicesPath);
  const expoProjectId = resolveProjectId();
  const expoOwner = process.env.EXPO_OWNER || "shiyas-expo-apps";

  const extra = {
    apiUrl: "https://glitchahitch.com/api",
    googleServicesFileConfigured: hasGoogleServicesFile,
  };

  if (expoProjectId) {
    extra.eas = { projectId: expoProjectId };
    extra.expoProjectId = expoProjectId;
  }

  return {
    expo: {
      owner: expoOwner,
      name: "Glitch A Hitch",
      slug: "glitchahitch",
      scheme: "glitchahitch",
      version: "0.1.0",
      orientation: "portrait",
      icon: "./assets/adaptive-icon.png",
      userInterfaceStyle: "automatic",
      splash: {
        image: "./assets/splash.png",
        resizeMode: "contain",
        backgroundColor: "#f5f5f5",
      },
      assetBundlePatterns: ["**/*"],
      ios: {
        supportsTablet: true,
        bundleIdentifier: "com.glitchahitch.app",
        useFrameworks: "static",
      },
      android: {
        package: "com.glitchahitch.app",
        adaptiveIcon: {
          foregroundImage: "./assets/adaptive-icon.png",
          backgroundColor: "#ffffff",
        },
        ...(hasGoogleServicesFile ? { googleServicesFile } : {}),
      },
      plugins: ["expo-notifications", "expo-dev-client"],
      extra: {
        eas: {
          projectId: "ed67a7c8-85af-457c-a459-f40ddcdd31b7",
          owner: "shiyas-expo-apps",
        },
      },
    },
  };
};
