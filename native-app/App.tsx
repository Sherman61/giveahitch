import { useState } from 'react';
import { View, StyleSheet } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import {
  GestureHandlerRootView,
  PanGestureHandler,
  PanGestureHandlerStateChangeEvent,
} from 'react-native-gesture-handler';
import { HomeScreen } from './src/screens/HomeScreen';
import { RidesScreen } from './src/screens/RidesScreen';
import { LoginScreen } from './src/screens/LoginScreen';
import { PostRideScreen } from './src/screens/PostRideScreen';
import { TabBar, TabItem } from './src/components/TabBar';
import { AuthResponse, logout } from './src/api/auth';

type TabKey = 'rides' | 'postRide' | 'notifications' | 'login';
const tabOrder: TabKey[] = ['rides', 'postRide', 'notifications', 'login'];
const SWIPE_THRESHOLD = 60;

const tabs: TabItem[] = tabOrder.map((key) => ({
  key,
  label:
    key === 'rides'
      ? 'Rides'
      : key === 'postRide'
        ? 'Post Ride'
        : key === 'notifications'
          ? 'Alerts'
          : 'Login',
}));

export default function App() {
  const [activeTab, setActiveTab] = useState<TabKey>('rides');
  const [auth, setAuth] = useState<AuthResponse | null>(null);

  const handleLoginSuccess = (data: AuthResponse) => {
    setAuth(data);
    setActiveTab('rides');
  };

  const handleLogout = async () => {
    await logout();
    setAuth(null);
    setActiveTab('login');
  };

  const goToIndex = (nextIndex: number) => {
    const safeIndex = Math.max(0, Math.min(tabOrder.length - 1, nextIndex));
    setActiveTab(tabOrder[safeIndex]);
  };

  const handleSwipe = ({ nativeEvent }: PanGestureHandlerStateChangeEvent) => {
    const { translationX } = nativeEvent;
    const currentIndex = tabOrder.indexOf(activeTab);
    if (translationX > SWIPE_THRESHOLD) {
      goToIndex(currentIndex - 1);
    } else if (translationX < -SWIPE_THRESHOLD) {
      goToIndex(currentIndex + 1);
    }
  };

  const resolvedTabs = tabs.map((tab) =>
    tab.key === 'login' ? { ...tab, label: auth ? 'Account' : 'Login' } : tab,
  );

  return (
    <GestureHandlerRootView style={styles.flex}>
      <SafeAreaProvider>
        <StatusBar style="dark" />
        <PanGestureHandler onEnded={handleSwipe}>
          <View style={styles.root}>
            <View style={styles.content}>
              {activeTab === 'rides' && (
                <RidesScreen user={auth?.user} onRequestLogin={() => setActiveTab('login')} />
              )}
              {activeTab === 'postRide' && (
                <PostRideScreen currentUser={auth?.user ?? null} onRequireLogin={() => setActiveTab('login')} />
              )}
              {activeTab === 'notifications' && <HomeScreen />}
              {activeTab === 'login' && (
                <LoginScreen
                  currentUser={auth?.user ?? null}
                  onLoginSuccess={handleLoginSuccess}
                  onLogout={handleLogout}
                />
              )}
            </View>
            <TabBar tabs={resolvedTabs} activeKey={activeTab} onChange={(key) => setActiveTab(key as TabKey)} />
          </View>
        </PanGestureHandler>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}

const styles = StyleSheet.create({
  flex: {
    flex: 1,
  },
  root: {
    flex: 1,
  },
  content: {
    flex: 1,
  },
});
