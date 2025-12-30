import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { View, StyleSheet, Dimensions, Animated } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AlertsScreen } from './src/screens/AlertsScreen';
import { RidesScreen } from './src/screens/RidesScreen';
import { LoginScreen } from './src/screens/LoginScreen';
import { PostRideScreen } from './src/screens/PostRideScreen';
import { MyRidesScreen } from './src/screens/MyRidesScreen';
import { SettingsScreen } from './src/screens/SettingsScreen';
import { TabBar, TabItem } from './src/components/TabBar';
import { HeaderBar } from './src/components/HeaderBar';
import { AuthResponse, logout } from './src/api/auth';
import { ProfileScreen } from './src/screens/ProfileScreen';
import { MessagesScreen } from './src/screens/MessagesScreen';
import { RateRidesScreen } from './src/screens/RateRidesScreen';
// import { EditProfileScreen } from './src/screens/EditProfileScreen';
import { NotificationsProvider, useNotifications } from './src/hooks/useNotifications';
import { PushNotificationTestScreen } from './src/screens/admin/PushNotificationTestScreen';

type TabKey =
  | 'rides'
  | 'myRides'
  | 'postRide'
  | 'messages'
  | 'alerts'
  | 'rate'
  | 'settings'
  | 'login'
  | 'adminPush';

const baseTabOrder: TabKey[] = [
  'rides',
  'myRides',
  'postRide',
  'messages',
  'alerts',
  'rate',
  'settings',
  'login',
 
];
const adminTabs: TabKey[] = ['adminPush'];
const tabBarTabs: TabKey[] = ['rides', 'myRides', 'postRide', 'messages'];
const windowWidth = Dimensions.get('window').width;

const tabs: TabItem[] = tabBarTabs.map((key) => ({
  key,
  label:
    key === 'rides'
      ? 'Rides'
      : key === 'myRides'
        ? 'My Rides'
        : key === 'postRide'
          ? 'Post Ride'
          : key === 'messages'
            ? 'Messages'
            : 'Account',
}));

function AppContent() {
  const [activeTab, setActiveTab] = useState<TabKey>('rides');
  const [auth, setAuth] = useState<AuthResponse | null>(null);
  const scrollRef = useRef<Animated.ScrollView>(null);
  const { expoPushToken, registerAsync } = useNotifications();
  const [autoRegistrationAttempted, setAutoRegistrationAttempted] = useState(false);
  const isAuthenticated = Boolean(auth?.user);
  const orderedTabs = useMemo(
    () => (isAuthenticated ? [...baseTabOrder, ...adminTabs] : baseTabOrder),
    [isAuthenticated],
  );

  const handleLoginSuccess = (data: AuthResponse) => {
    setAuth(data);
    scrollToTab('rides');
  };

  const handleLogout = async () => {
    await logout();
    setAuth(null);
    scrollToTab('login');
  };

  const scrollToTab = useCallback(
    (key: TabKey) => {
      const nextIndex = orderedTabs.indexOf(key);
      if (nextIndex < 0) return;
      setActiveTab(key);
      scrollRef.current?.scrollTo({ x: nextIndex * windowWidth, animated: true });
    },
    [orderedTabs],
  );

  const onMomentumEnd = (event: any) => {
    const index = Math.round(event.nativeEvent.contentOffset.x / windowWidth);
    const key = orderedTabs[index] ?? 'rides';
    if (key !== activeTab) {
      setActiveTab(key);
    }
  };

  const resolvedTabs = tabs.map((tab) =>
    tab.key === 'login' ? { ...tab, label: auth ? 'Profile' : 'Login' } : tab,
  );
  const headerMenuItems = useMemo(() => {
    const items: { key: TabKey; label: string }[] = [
      { key: 'rides', label: 'Rides' },
      { key: 'myRides', label: 'My rides' },
      { key: 'postRide', label: 'Post ride' },
      { key: 'messages', label: 'Messages' },
      { key: 'alerts', label: 'Alerts' },
      { key: 'rate', label: 'Rate rides' },
     
      { key: 'settings', label: 'Settings' },
      { key: 'login', label: auth ? 'Account' : 'Login' },
    ];
    if (isAuthenticated) {
      items.push({ key: 'adminPush', label: 'Admin: Push test' });
    }
    return items;
  }, [auth, isAuthenticated]);

  useEffect(() => {
    if (!orderedTabs.includes(activeTab)) {
      setActiveTab('rides');
      scrollRef.current?.scrollTo({ x: 0, animated: false });
    }
  }, [orderedTabs, activeTab]);

  useEffect(() => {
    if (!auth?.user) {
      setAutoRegistrationAttempted(false);
      return;
    }
    if (expoPushToken || autoRegistrationAttempted) {
      return;
    }
    let cancelled = false;
    setAutoRegistrationAttempted(true);
    (async () => {
      try {
        await registerAsync();
      } catch (error) {
        console.warn('Automatic push registration failed', error);
        if (!cancelled) {
          setAutoRegistrationAttempted(false);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [auth?.user, expoPushToken, registerAsync, autoRegistrationAttempted]);

  const renderScene = (key: TabKey) => {
    switch (key) {
      case 'rides':
        return (
          <RidesScreen
            user={auth?.user}
            onNavigate={(navKey) => scrollToTab(navKey as TabKey)}
            onRequestLogin={() => scrollToTab('login')}
          />
        );
      case 'myRides':
        return (
          <MyRidesScreen
            user={auth?.user}
            onNavigate={(navKey) => scrollToTab(navKey as TabKey)}
            onRequestLogin={() => scrollToTab('login')}
          />
        );
      case 'postRide':
        return (
          <PostRideScreen
            currentUser={auth?.user ?? null}
            onNavigate={(navKey) => scrollToTab(navKey as TabKey)}
            onRequireLogin={() => scrollToTab('login')}
          />
        );
      case 'alerts':
        return <AlertsScreen />;
      case 'messages':
        return <MessagesScreen user={auth?.user ?? null} onRequestLogin={() => scrollToTab('login')} />;
      case 'rate':
        return <RateRidesScreen user={auth?.user ?? null} onRequestLogin={() => scrollToTab('login')} />;
      case 'settings':
        return <SettingsScreen />;
      
      case 'adminPush':
        return (
          <PushNotificationTestScreen
            isAuthenticated={isAuthenticated}
            onRequireLogin={() => scrollToTab('login')}
          />
        );
      case 'login':
      default:
        return auth?.user ? (
          <ProfileScreen user={auth.user} onLogout={handleLogout} />
        ) : (
          <LoginScreen currentUser={null} onLoginSuccess={handleLoginSuccess} onLogout={handleLogout} />
        );
    }
  };

  return (
    <SafeAreaProvider>
      <StatusBar style="dark" />
      <View style={styles.root}>
        <HeaderBar
          activeTab={activeTab}
          onMenuSelect={(key) => scrollToTab(key as TabKey)}
          onLogout={handleLogout}
          menuItems={headerMenuItems}
        />
        <Animated.ScrollView
          ref={scrollRef}
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          onMomentumScrollEnd={onMomentumEnd}
          scrollEventThrottle={16}
        >
          {orderedTabs.map((key) => (
            <View key={key} style={{ width: windowWidth }}>
              {renderScene(key)}
            </View>
          ))}
        </Animated.ScrollView>
        <TabBar tabs={resolvedTabs} activeKey={activeTab} onChange={(key) => scrollToTab(key as TabKey)} />
      </View>
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
  root: {
    flex: 1,
    backgroundColor: '#f1f1f1',
  },
});
