import { useEffect, useMemo, useState } from 'react';
import { View, StyleSheet } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { AlertsScreen } from './src/screens/AlertsScreen';
import { RidesScreen } from './src/screens/RidesScreen';
import { LoginScreen } from './src/screens/LoginScreen';
import { PostRideScreen } from './src/screens/PostRideScreen';
import { MyRidesScreen } from './src/screens/MyRidesScreen';
import { SettingsScreen } from './src/screens/SettingsScreen';
import { TabBar, TabItem } from './src/components/TabBar';
import { AuthResponse, logout } from './src/api/auth';
import { ProfileScreen } from './src/screens/ProfileScreen';
import { MessagesScreen } from './src/screens/MessagesScreen';
import { RateRidesScreen } from './src/screens/RateRidesScreen';
import { NotificationsProvider } from './src/hooks/useNotifications';
import { PushNotificationTestScreen } from './src/screens/admin/PushNotificationTestScreen';
import { AccountScreen } from './src/screens/AccountScreen';
import { palette } from './src/constants/colors';
import { heartbeatPresence } from './src/api/presence';
import { useScreenAnalytics } from './src/hooks/useScreenAnalytics';

type PrimaryTab = 'browse' | 'myRides' | 'post' | 'messages';
type SecondaryRoute = 'alerts' | 'account' | 'profile' | 'settings' | 'rate' | 'adminPush' | null;

const tabs: TabItem[] = [
  { key: 'browse', label: 'Browse' },
  { key: 'myRides', label: 'My Rides' },
  { key: 'post', label: 'Post' },
  { key: 'messages', label: 'Messages' },
];

function AppContent() {
  const [activeTab, setActiveTab] = useState<PrimaryTab>('browse');
  const [secondaryRoute, setSecondaryRoute] = useState<SecondaryRoute>(null);
  const [auth, setAuth] = useState<AuthResponse | null>(null);

  const isAuthenticated = Boolean(auth?.user);
  const currentUser = auth?.user ?? null;
  const appScreenName = useMemo(() => {
    if (secondaryRoute) {
      switch (secondaryRoute) {
        case 'alerts':
          return 'AlertsScreen';
        case 'account':
          return isAuthenticated ? 'AccountScreen' : 'LoginScreen';
        case 'profile':
          return 'ProfileScreen';
        case 'settings':
          return 'SettingsScreen';
        case 'rate':
          return 'RateRidesScreen';
        case 'adminPush':
          return 'PushNotificationTestScreen';
        default:
          return 'UnknownScreen';
      }
    }

    switch (activeTab) {
      case 'browse':
        return 'RidesScreen';
      case 'myRides':
        return 'MyRidesScreen';
      case 'post':
        return 'PostRideScreen';
      case 'messages':
        return 'MessagesScreen';
      default:
        return 'RidesScreen';
    }
  }, [activeTab, isAuthenticated, secondaryRoute]);

  useScreenAnalytics(appScreenName);

  useEffect(() => {
    if (!isAuthenticated) {
      return;
    }

    void heartbeatPresence().catch(() => {});
    const interval = setInterval(() => {
      void heartbeatPresence().catch(() => {});
    }, 5000);

    return () => {
      clearInterval(interval);
    };
  }, [isAuthenticated]);

  const shellActions = useMemo(
    () => ({
      openAccount: () => setSecondaryRoute('account'),
      openAlerts: () => setSecondaryRoute('alerts'),
      requestLogin: () => setSecondaryRoute('account'),
      openBrowse: () => {
        setSecondaryRoute(null);
        setActiveTab('browse');
      },
      openMyRides: () => {
        setSecondaryRoute(null);
        setActiveTab('myRides');
      },
      openPost: () => {
        setSecondaryRoute(null);
        setActiveTab('post');
      },
      openMessages: () => {
        setSecondaryRoute(null);
        setActiveTab('messages');
      },
    }),
    [],
  );

  const handleLoginSuccess = (data: AuthResponse) => {
    setAuth(data);
    setSecondaryRoute(null);
    setActiveTab('browse');
  };

  const handleLogout = async () => {
    await logout();
    setAuth(null);
    setSecondaryRoute('account');
    setActiveTab('browse');
  };

  const handlePrimaryTabChange = (key: string) => {
    setSecondaryRoute(null);

    switch (key) {
      case 'browse':
        setActiveTab('browse');
        break;
      case 'myRides':
        setActiveTab('myRides');
        break;
      case 'post':
        setActiveTab('post');
        break;
      case 'messages':
        setActiveTab('messages');
        break;
      default:
        setActiveTab('browse');
        break;
    }
  };

  const renderPrimary = () => {
    switch (activeTab) {
      case 'browse':
        return (
          <RidesScreen
            user={currentUser}
            onRequestLogin={shellActions.requestLogin}
            onOpenAlerts={shellActions.openAlerts}
            onOpenAccount={shellActions.openAccount}
            onOpenMyRides={shellActions.openMyRides}
            onOpenPost={shellActions.openPost}
          />
        );
      case 'myRides':
        return (
          <MyRidesScreen
            user={currentUser}
            onRequestLogin={shellActions.requestLogin}
            onOpenAlerts={shellActions.openAlerts}
            onOpenAccount={shellActions.openAccount}
            onOpenBrowse={shellActions.openBrowse}
            onOpenMessages={shellActions.openMessages}
            onOpenPost={shellActions.openPost}
          />
        );
      case 'post':
        return (
          <PostRideScreen
            currentUser={currentUser}
            onRequireLogin={shellActions.requestLogin}
            onOpenAlerts={shellActions.openAlerts}
            onOpenAccount={shellActions.openAccount}
            onPosted={shellActions.openBrowse}
          />
        );
      case 'messages':
      default:
        return (
          <MessagesScreen
            user={currentUser}
            onRequestLogin={shellActions.requestLogin}
            onOpenAccount={shellActions.openAccount}
          />
        );
    }
  };

  const renderSecondary = () => {
    switch (secondaryRoute) {
      case 'alerts':
        return <AlertsScreen onBack={() => setSecondaryRoute(null)} />;
      case 'account':
        return isAuthenticated && currentUser ? (
          <AccountScreen
            user={currentUser}
            onBack={() => setSecondaryRoute(null)}
            onOpenProfile={() => setSecondaryRoute('profile')}
            onOpenSettings={() => setSecondaryRoute('settings')}
            onOpenRatings={() => setSecondaryRoute('rate')}
            onOpenAdminPush={() => setSecondaryRoute('adminPush')}
            onLogout={handleLogout}
          />
        ) : (
          <LoginScreen
            currentUser={null}
            onLoginSuccess={handleLoginSuccess}
            onLogout={handleLogout}
            onBack={() => setSecondaryRoute(null)}
          />
        );
      case 'profile':
        return currentUser ? <ProfileScreen user={currentUser} onLogout={handleLogout} onBack={() => setSecondaryRoute('account')} /> : null;
      case 'settings':
        return <SettingsScreen onBack={() => setSecondaryRoute('account')} />;
      case 'rate':
        return <RateRidesScreen user={currentUser} onRequestLogin={shellActions.requestLogin} onBack={() => setSecondaryRoute('account')} />;
      case 'adminPush':
        return (
          <PushNotificationTestScreen
            isAuthenticated={isAuthenticated}
            onRequireLogin={shellActions.requestLogin}
            onBack={() => setSecondaryRoute('account')}
          />
        );
      default:
        return null;
    }
  };

  return (
    <SafeAreaProvider>
      <StatusBar style="dark" />
      <SafeAreaView style={styles.safeArea} edges={['top', 'right', 'bottom', 'left']}>
        <View style={styles.root}>
          <View style={styles.content}>{secondaryRoute ? renderSecondary() : renderPrimary()}</View>
          {!secondaryRoute ? (
            <TabBar tabs={tabs} activeKey={activeTab} onChange={handlePrimaryTabChange} />
          ) : null}
        </View>
      </SafeAreaView>
    </SafeAreaProvider>
  );
}

export default function App() {
  return (
    <NotificationsProvider>
      <AppContent />
    </NotificationsProvider>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: palette.background,
  },
  root: {
    flex: 1,
    backgroundColor: palette.background,
  },
  content: {
    flex: 1,
  },
});
